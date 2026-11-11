<?php
/**
 * articles – Articles
 * Reading, saving, copying, moving and deleting articles (from Phase 3 on).
 * Phase 1: removing everything that belongs to a section.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Articles
{
    /**
     * Deletes all rows of a section, tags no section uses any more (news_img
     * left them behind) and the section's image folders.
     */
    public static function deleteSection(\Database $db, int $sectionId): void
    {
        $articleIds = array_map('intval', array_column(
            $db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]),
            'article_id'
        ));
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
        // shortcut: access files are removed from Phase 3 on (AccessFiles), until then there are none
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
