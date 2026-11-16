<?php
/**
 * articles – search_index.php
 * Tells WBCE Search (core module wbce_search, WBCE 1.7+) what to index for an
 * articles section: one entry per article a visitor can open, with its detail
 * page and preview image. Only WBCE Search reads this file; the format follows
 * modules/wbce_search/SEARCH_INDEX_HOWTO.md (worked example: news_img).
 *
 * Kept up to date like news_img: backend.php calls $admin->touchSection() after
 * every change, and WBCE Search re-indexes a section whose page changed since.
 * shortcut: an article that becomes visible or expires by its dates alone changes no page, so the index follows only with the next edit; needs a time-based re-index in WBCE Search.
 * The classic search (search.php) works without this file.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

return [
    'callback' => static function (
        Database $database, array $def, array $section,
        int $pid, int $sid, string $module,
        string $page_title, string $description, string $keywords,
        string $visibility, int $searching, int $now
    ): int {
        // Not at file level: WBCE Search may read this file without indexing (SEARCH_INDEX_HOWTO.md, section 2)
        require_once __DIR__ . '/bootstrap.php';

        // title/description/keywords are the page's, item_title the article's.
        // WBCE Search has deleted the section's old rows before calling.
        $insert = static function (int $itemId, string $itemTitle, string $html, string $detailUrl, string $thumbUrl) use (
            $database, $pid, $sid, $module, $page_title, $description, $keywords, $visibility, $searching, $now
        ): int {
            $database->insertRow('{TP_SEARCH}index', [
                'page_id'     => $pid,
                'section_id'  => $sid,
                'item_id'     => $itemId,
                'module'      => $module,
                'title'       => $page_title,
                'item_title'  => $itemTitle,
                'description' => $description,
                'keywords'    => $keywords,
                'content'     => WbceSearchIndexer::plainText($html),
                'detail_url'  => WbceSearch::urlToToken($detailUrl),
                'thumb_url'   => WbceSearch::urlToToken($thumbUrl),
                'visibility'  => $visibility,
                'searching'   => $searching,
                'indexed_at'  => $now,
            ]);
            return $database->hasError() ? 0 : 1;
        };

        // Exactly the articles the frontend shows (Articles::visibleSql), and none while
        // the section is outside its publication period: WBCE Search checks only the
        // page's rights, whatever is indexed is found by everyone who may see the page.
        $count = 0;
        foreach ($database->fetchAll(
            'SELECT i.`article_id`, i.`title`, i.`link`, i.`image`, i.`teaser`, i.`content`, i.`block2`
               FROM `{TP}mod_articles_items` i JOIN `{TP}sections` s ON s.`section_id` = i.`section_id`
              WHERE i.`section_id` = ? AND ' . Articles\Articles::visibleSql('i', $now) . "
                AND (s.`publ_start` = 0 OR s.`publ_start` <= $now) AND (s.`publ_end` = 0 OR s.`publ_end` >= $now)
              ORDER BY i.`article_id`",
            [$sid]
        ) as $a) {
            $id = (int) $a['article_id'];
            $count += $insert(
                $id,
                // imported titles may carry entities ("&amp;"); the index holds plain text
                html_entity_decode((string) $a['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $a['teaser'] . ' ' . $a['content'] . ' ' . $a['block2'],
                Articles\Frontend::pageUrl((string) $a['link']),
                $a['image'] !== '' ? Articles\Frontend::mediaUrl("/items/$id/preview/" . $a['image']) : ''
            );
        }

        // No visible article: keep the page itself findable by its title.
        // Returning 0 would store a marker row ("processed, nothing to find").
        if ($count === 0 && $page_title !== '') {
            $count = $insert(0, '', '', '', '');
        }
        return $count;
    },
];
