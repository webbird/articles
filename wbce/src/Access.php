<?php
/**
 * articles – Access
 * Rights of the backend in one place: every object is resolved to its section
 * and page, and the page must be one the user may administer (news_img 5.0.32
 * checked this in every action file separately).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Access
{
    /** @var array<int, bool> */
    private array $sections = [];

    public function __construct(private readonly \Database $db, private readonly \Admin $admin)
    {
    }

    public function isAdmin(): bool
    {
        return $this->admin->isAdmin();
    }

    /** An articles section on a page the user may administer; 0 (global) only for admins. */
    public function section(int $sectionId): bool
    {
        if ($sectionId === 0) {
            return $this->isAdmin();
        }
        if (!isset($this->sections[$sectionId])) {
            $pageId = (int) $this->db->fetchValue(
                "SELECT `page_id` FROM `{TP}sections` WHERE `section_id` = ? AND `module` = 'articles'",
                [$sectionId]
            );
            $this->sections[$sectionId] = $pageId > 0 && $this->admin->get_page_permission($pageId, 'admin');
        }
        return $this->sections[$sectionId];
    }

    /** "Settings for admins only" counts on the server as well. */
    public function settings(int $sectionId): bool
    {
        return $this->section($sectionId) && ($this->isAdmin() || !(int) $this->db->fetchValue(
            'SELECT `settings_admins_only` FROM `{TP}mod_articles_settings` WHERE `section_id` = ?',
            [$sectionId]
        ));
    }

    /** True if every article exists and lies in $sectionId (the section the user works in). */
    public function articles(array $ids, int $sectionId): bool
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || in_array(0, $ids, true) || !$this->section($sectionId)) {
            return false;
        }
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `{TP}mod_articles_items` WHERE `section_id` = ? AND `article_id` IN (' . implode(',', $ids) . ')',
            [$sectionId]
        ) === count($ids);
    }

    /** A group of $sectionId; 0 means "no group" and is always fine. */
    public function group(int $groupId, int $sectionId): bool
    {
        return $groupId === 0 || (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `{TP}mod_articles_groups` WHERE `group_id` = ? AND `section_id` = ?',
            [$groupId, $sectionId]
        ) === 1;
    }

    /** May the user change or delete the tag: global ones admins only, local ones all their sections. */
    public function tag(int $tagId): bool
    {
        $sections = array_map('intval', array_column(
            $this->db->fetchAll('SELECT `section_id` FROM `{TP}mod_articles_tag_sections` WHERE `tag_id` = ?', [$tagId]),
            'section_id'
        ));
        if ($sections === []) {
            return false;
        }
        foreach ($sections as $sectionId) {
            if (!$this->section($sectionId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Articles sections the user may administer, as targets for moving and copying.
     *
     * @return list<array{section_id: int, page_id: int, label: string}>
     */
    public function targets(): array
    {
        $targets = [];
        foreach ($this->db->fetchAll(
            "SELECT s.`section_id`, s.`page_id`, p.`menu_title`, p.`page_title`
               FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id`
              WHERE s.`module` = 'articles' ORDER BY p.`page_trail`, s.`position`"
        ) as $row) {
            if ($this->section((int) $row['section_id'])) {
                $targets[] = [
                    'section_id' => (int) $row['section_id'],
                    'page_id'    => (int) $row['page_id'],
                    'label'      => ($row['menu_title'] ?: $row['page_title']) . ' (#' . (int) $row['section_id'] . ')',
                ];
            }
        }
        return $targets;
    }
}
