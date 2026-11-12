<?php
/**
 * articles – Groups
 * Groups of a section: list, save, delete, active switch, order.
 * The group image follows with the images (Phase 5).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Groups
{
    /** @return list<array> groups in their order, with the number of articles */
    public static function list(\Database $db, int $sectionId): array
    {
        return $db->fetchAll(
            'SELECT g.*, (SELECT COUNT(*) FROM `{TP}mod_articles_items` i WHERE i.`group_id` = g.`group_id`) AS `articles`
               FROM `{TP}mod_articles_groups` g WHERE g.`section_id` = ? ORDER BY g.`position`, g.`group_id`',
            [$sectionId]
        );
    }

    /** New groups come last; returns the id. */
    public static function save(\Database $db, int $sectionId, int $groupId, string $title, bool $active): int
    {
        if ($groupId === 0) {
            $position = (int) $db->fetchValue('SELECT MAX(`position`) FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]) + 1;
            return Articles::insert($db, 'groups', [
                'section_id' => $sectionId, 'position' => $position, 'active' => $active ? 1 : 0, 'title' => $title, 'image' => '',
            ]);
        }
        Articles::exec($db, 'UPDATE `{TP}mod_articles_groups` SET `title` = ?, `active` = ? WHERE `group_id` = ?', [$title, $active ? 1 : 0, $groupId]);
        self::syncArticles($db, $groupId);
        return $groupId;
    }

    public static function setActive(\Database $db, int $groupId, bool $active): void
    {
        Articles::exec($db, 'UPDATE `{TP}mod_articles_groups` SET `active` = ? WHERE `group_id` = ?', [$active ? 1 : 0, $groupId]);
        self::syncArticles($db, $groupId);
    }

    /** Its articles keep existing without a group and may become visible. */
    public static function delete(\Database $db, int $groupId): void
    {
        $ids = self::articleIds($db, $groupId);
        $db->transaction(static function (\Database $db) use ($groupId): void {
            Articles::exec($db, 'UPDATE `{TP}mod_articles_items` SET `group_id` = 0 WHERE `group_id` = ?', [$groupId]);
            Articles::exec($db, 'DELETE FROM `{TP}mod_articles_groups` WHERE `group_id` = ?', [$groupId]);
        });
        foreach ($ids as $id) {
            AccessFiles::sync($db, $id);
        }
        if (is_dir(\ARTICLES_MEDIA_PATH . "/groups/$groupId")) {
            rm_full_dir(\ARTICLES_MEDIA_PATH . "/groups/$groupId");
        }
    }

    /** New order, first group first. */
    public static function reorder(\Database $db, int $sectionId, array $ids): void
    {
        $db->transaction(static function (\Database $db) use ($sectionId, $ids): void {
            foreach (array_values($ids) as $i => $id) {
                Articles::exec($db, 'UPDATE `{TP}mod_articles_groups` SET `position` = ? WHERE `group_id` = ? AND `section_id` = ?', [$i + 1, (int) $id, $sectionId]);
            }
        });
    }

    public static function step(\Database $db, int $sectionId, int $groupId, bool $up): void
    {
        $ids = array_map('intval', array_column(self::list($db, $sectionId), 'group_id'));
        $pos = array_search($groupId, $ids, true);
        $other = is_int($pos) ? $pos + ($up ? -1 : 1) : -1;
        if (isset($ids[$other])) {
            [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];
            self::reorder($db, $sectionId, $ids);
        }
    }

    /** @return list<int> */
    private static function articleIds(\Database $db, int $groupId): array
    {
        return array_map('intval', array_column(
            $db->fetchAll('SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `group_id` = ?', [$groupId]),
            'article_id'
        ));
    }

    /** The active switch of a group decides about the access files of its articles (Variante A). */
    private static function syncArticles(\Database $db, int $groupId): void
    {
        foreach (self::articleIds($db, $groupId) as $id) {
            AccessFiles::sync($db, $id);
        }
    }
}
