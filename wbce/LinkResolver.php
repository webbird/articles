<?php
/**
 * articles – LinkResolver provider
 * Offers the articles of a section in the TinyMCE link picker and resolves
 * [articles:NN] in rendered content (framework/LinkResolver.php).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

class ArticlesLinkResolver extends LinkResolver
{
    /** Visible and planned articles; expired ones or those in an inactive group would leave "[articles:NN]" in the page. */
    public function linkItems(int $sectionId, int $pageId): array
    {
        $items = [];
        foreach ($GLOBALS['database']->fetchAll(
            'SELECT i.`article_id`, i.`title` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? AND '
            . Articles\Articles::visibleSql('i', null, true) . ' ORDER BY i.`article_id` DESC',
            [$sectionId]
        ) as $row) {
            $items[] = ['label' => (string) $row['title'], 'value' => '[articles:' . (int) $row['article_id'] . ']'];
        }
        return $items;
    }

    /** Only visible articles: everything else has no access file, the link would end in a 404. */
    public function resolve(int $itemId): ?string
    {
        $link = (string) $GLOBALS['database']->fetchValue(
            'SELECT i.`link` FROM `{TP}mod_articles_items` i WHERE i.`article_id` = ? AND ' . Articles\Articles::visibleSql('i'),
            [$itemId]
        );
        return $link !== '' ? Articles\Frontend::pageUrl($link) : null;
    }

    public function currentItemId(int $sectionId, int $pageId): ?int
    {
        return defined('ARTICLE_ID') && (int) ARTICLE_SECTION === $sectionId ? (int) ARTICLE_ID : null;
    }
}
