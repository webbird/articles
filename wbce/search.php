<?php
/**
 * articles – search.php
 * Hook of the core search (search/search.php calls articles_search() per
 * section). No {TP}search rows needed: the core uses them only for modules
 * without a search function.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

function articles_search(array $vars): bool
{
    $db = $vars['database'];
    $found = false;
    // Each visible article is a hit of its own, with its detail page as link.
    foreach ($db->fetchAll(
        'SELECT i.`title`, i.`teaser`, i.`content`, i.`block2`, i.`link`, i.`modified_at`, i.`modified_by`
           FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? AND ' . Articles\Articles::visibleSql('i')
        . ' ORDER BY i.`article_id` DESC',
        [(int) $vars['section_id']]
    ) as $row) {
        $found = print_excerpt2([
            'page_link'          => $row['link'],
            'page_link_target'   => '',
            'page_title'         => $vars['page_title'],
            'page_description'   => $row['title'],
            'page_modified_when' => $row['modified_at'],
            'page_modified_by'   => $row['modified_by'],
            'text'               => $row['title'] . '.' . $row['teaser'] . '.' . $row['content'] . '.' . $row['block2'] . '.',
            'max_excerpt_num'    => $vars['default_max_excerpt'],
        ], $vars) || $found;
    }
    // Groups with visible articles lead to the filtered list.
    foreach ($db->fetchAll(
        'SELECT g.`group_id`, g.`title` FROM `{TP}mod_articles_groups` g
          WHERE g.`section_id` = ? AND g.`active` = 1
            AND EXISTS (SELECT 1 FROM `{TP}mod_articles_items` i WHERE i.`group_id` = g.`group_id` AND ' . Articles\Articles::visibleSql('i') . ')',
        [(int) $vars['section_id']]
    ) as $group) {
        $found = print_excerpt2([
            'page_link'          => $vars['page_link'],
            'page_link_target'   => '&g=' . (int) $group['group_id'],
            'page_title'         => $vars['page_title'],
            'page_description'   => $vars['page_description'],
            'page_modified_when' => $vars['page_modified_when'],
            'page_modified_by'   => $vars['page_modified_by'],
            'text'               => $group['title'] . '.',
            'max_excerpt_num'    => $vars['default_max_excerpt'],
        ], $vars) || $found;
    }
    return $found;
}
