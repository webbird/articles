<?php
/**
 * articles – Articles
 * Visibility and order of articles, removing everything that belongs to a section.
 * Saving, copying and moving follow with the backend (Phase 4).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Articles
{
    /** Setting sort_order => column and direction; the six orders of news_img (3 was posted_when). */
    public const ORDER = [
        0 => ['position', 'DESC'],
        1 => ['published_from', 'DESC'],
        2 => ['published_until', 'DESC'],
        3 => ['modified_at', 'DESC'],
        4 => ['article_id', 'DESC'],
        5 => ['published_until', 'ASC'],
    ];

    /**
     * The one visibility rule for list, count, detail, neighbours, RSS, search,
     * droplets and access files: active, has a title, inside its period, group
     * active or no group. $alias is the items table.
     */
    public static function visibleSql(string $alias = 'i', ?int $now = null): string
    {
        $now ??= time();
        $a = '`' . $alias . '`';
        return "$a.`active` = 1 AND $a.`title` <> ''"
            . " AND ($a.`published_from` = 0 OR $a.`published_from` <= $now)"
            . " AND ($a.`published_until` = 0 OR $a.`published_until` >= $now)"
            . " AND ($a.`group_id` = 0 OR EXISTS (SELECT 1 FROM `{TP}mod_articles_groups` gv"
            . " WHERE gv.`group_id` = $a.`group_id` AND gv.`active` = 1))";
    }

    /** ORDER BY for a sort_order; article_id breaks ties so pages and neighbours are stable. */
    public static function orderSql(int $sortOrder, string $alias = 'i'): string
    {
        [$column, $direction] = self::ORDER[$sortOrder] ?? self::ORDER[0];
        return "`$alias`.`$column` $direction, `$alias`.`article_id` $direction";
    }

    /**
     * Deletes all rows of a section, tags no section uses any more (news_img
     * left them behind) and the section's image folders.
     */
    public static function deleteSection(\Database $db, int $sectionId): void
    {
        $articles = $db->fetchAll('SELECT `article_id`, `link` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]);
        $articleIds = array_map('intval', array_column($articles, 'article_id'));
        $links = array_column($articles, 'link');
        $groupIds = array_map('intval', array_column(
            $db->fetchAll('SELECT `group_id` FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]),
            'group_id'
        ));

        $db->transaction(static function (\Database $db) use ($sectionId): void {
            $ofSection = 'IN (SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?)';
            foreach ([
                "DELETE FROM `{TP}mod_articles_images` WHERE `article_id` $ofSection",
                "DELETE FROM `{TP}mod_articles_item_tags` WHERE `article_id` $ofSection",
                "DELETE FROM `{TP}mod_articles_import` WHERE `article_id` $ofSection",
                'DELETE FROM `{TP}mod_articles_items` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_groups` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_settings` WHERE `section_id` = ?',
                // Global tags keep their section 0 row and survive.
                'DELETE FROM `{TP}mod_articles_tags` WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `{TP}mod_articles_tag_sections`)',
            ] as $sql) {
                $db->query($sql, str_contains($sql, '?') ? [$sectionId] : []);
                // transaction() only sees the error of the last query, so check each one
                if ($db->hasError()) {
                    throw new \RuntimeException('articles: delete section ' . $sectionId . ': ' . $db->getError());
                }
            }
        });

        // Files after the commit: a failed transaction must not cost any images.
        foreach ($links as $link) {
            AccessFiles::remove($link);
        }
        $folders = array_merge(
            array_map(static fn (int $id): string => "/items/$id", $articleIds),
            array_map(static fn (int $id): string => "/groups/$id", $groupIds),
            ["/sections/$sectionId"]
        );
        foreach ($folders as $folder) {
            if (is_dir(\ARTICLES_MEDIA_PATH . $folder)) {
                rm_full_dir(\ARTICLES_MEDIA_PATH . $folder);
            }
        }
    }
}
