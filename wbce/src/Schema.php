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

    /**
     * Imports droplets/*.php in the format of the droplets module (<?php, //:description,
     * //:usage, code) and updates existing ones by name. Done here because the module's
     * own importDropletFromFile() needs the backend (getTwig). The droplets stay on
     * uninstall and then return ''.
     */
    public static function installDroplets(\Database $db): void
    {
        if (!$db->fetchValue("SELECT COUNT(*) FROM `{TP}addons` WHERE `type` = 'module' AND `directory` = 'droplets'")) {
            return;
        }
        foreach (glob(dirname(__DIR__) . '/droplets/*.php') ?: [] as $file) {
            [, $description, $usage] = $lines = file($file);
            $row = [
                'name'          => basename($file, '.php'),
                'code'          => implode('', array_slice($lines, 3)),
                'description'   => trim(substr($description, 3)),
                'comments'      => trim(substr($usage, 3)),
                'modified_when' => time(),
                'modified_by'   => 0,
                'active'        => 1,
                'admin_edit'    => 0,
                'admin_view'    => 0,
                'show_wysiwyg'  => 0,
            ];
            $id = $db->fetchValue('SELECT `id` FROM `{TP}mod_droplets` WHERE `name` = ?', [$row['name']]);
            $result = $id === '' ? $db->insertRow('{TP}mod_droplets', $row) : $db->upsertRow('{TP}mod_droplets', 'id', ['id' => (int) $id] + $row);
            if ($result !== true) {
                throw new \RuntimeException('articles install: droplet ' . $row['name'] . ': ' . (is_string($result) ? $result : $db->getError()));
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
