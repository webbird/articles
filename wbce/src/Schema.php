<?php
/**
 * articles – Schema
 * Creates and removes the tables. install.php and upgrade.php run install(),
 * which is safe to repeat; uninstall.php runs uninstall().
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Schema
{
    public const TABLES = [
        'settings', 'items', 'groups', 'images', 'tags', 'tag_sections', 'item_tags', 'import',
    ];

    /** Index name => [table, fields]. Names are global on SQLite, hence the prefix. */
    private const INDEXES = [
        'articles_items_section'  => ['items', 'section_id'],
        'articles_items_group'    => ['items', 'group_id'],
        'articles_groups_section' => ['groups', 'section_id'],
        'articles_images_article' => ['images', 'article_id'],
        'articles_tagsec_section' => ['tag_sections', 'section_id'],
        'articles_itemtags_tag'   => ['item_tags', 'tag_id'],
        'articles_import_source'  => ['import', 'source_module,source_id'],
    ];

    public static function install(\Database $db): void
    {
        foreach ($db->importSql(dirname(__DIR__) . '/install_struct.sql') as $step) {
            if (!$step['ok']) {
                throw new \RuntimeException('articles install: ' . $step['msg']);
            }
        }
        foreach (self::INDEXES as $name => [$table, $fields]) {
            if (!$db->addIndex('{TP}mod_articles_' . $table, $name, $fields)) {
                throw new \RuntimeException("articles install: index $name: " . $db->getError());
            }
        }
    }

    public static function uninstall(\Database $db): void
    {
        foreach (self::TABLES as $table) {
            $db->query('DROP TABLE IF EXISTS `{TP}mod_articles_' . $table . '`');
        }
        if (is_dir(\ARTICLES_MEDIA_PATH)) {
            rm_full_dir(\ARTICLES_MEDIA_PATH);
        }
    }
}
