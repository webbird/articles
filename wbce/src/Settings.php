<?php
/**
 * articles – Settings
 * Per-section settings in mod_articles_settings.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Settings
{
    /** Views shipped with the module; own views in views/<name>/ are allowed as well. */
    public const VIEWS     = ['default', 'grid', 'avatar'];
    public const GALLERIES = ['fotorama', 'masonry', 'lightcase', 'none'];

    /** Variables of views/<name>/config.php (format of news_img, so its views can be copied) => columns. */
    private const VIEW_TEMPLATES = [
        'header'       => 'tpl_list_header',
        'post_loop'    => 'tpl_list_item',
        'footer'       => 'tpl_list_footer',
        'post_header'  => 'tpl_detail_header',
        'post_content' => 'tpl_detail_content',
        'post_footer'  => 'tpl_detail_footer',
        'image_loop'   => 'tpl_image_loop',
        'block2'       => 'tpl_block2',
    ];

    /** Values of a new section; image sizes as in news_img's add.php, templates from views/default. */
    public const DEFAULTS = [
        'sort_order'           => 0,
        'per_page'             => 0,
        'preview_width'        => 125,
        'preview_height'       => 125,
        'preview_crop'         => 0,
        'thumb_width'          => 100,
        'thumb_height'         => 100,
        'thumb_crop'           => 0,
        'large_width'          => 900,
        'large_height'         => 900,
        'large_crop'           => 0,
        'use_block2'           => 0,
        'view'                 => 'default',
        'gallery'              => 'fotorama',
        'articles_dir'         => '',
        'default_image'        => '',
        'expert_mode'          => 0,
        'settings_admins_only' => 0,
    ];

    public static function create(\Database $db, int $sectionId): void
    {
        $result = $db->insertRow('{TP}mod_articles_settings', ['section_id' => $sectionId]
            + self::DEFAULTS
            + self::viewTemplates('default')
            + ['max_upload' => ini_parse_quantity((string) ini_get('upload_max_filesize'))]);
        if ($result !== true) {
            throw new \RuntimeException('articles: settings for section ' . $sectionId . ': ' . $result);
        }
    }

    /** A view name usable as a folder; unknown or unsafe names fall back to default. */
    public static function view(string $name): string
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) && is_file(dirname(__DIR__) . "/views/$name/config.php")
            ? $name : 'default';
    }

    /** @return list<string> the shipped views first, then own ones in views/<name>/ */
    public static function views(): array
    {
        $own = array_map('basename', array_map('dirname', glob(dirname(__DIR__) . '/views/*/config.php') ?: []));
        return array_values(array_unique(array_merge(self::VIEWS, array_filter($own, static fn (string $v): bool => self::view($v) === $v))));
    }

    public static function get(\Database $db, int $sectionId): ?array
    {
        return $db->fetchRow('SELECT * FROM `{TP}mod_articles_settings` WHERE `section_id` = ?', [$sectionId]);
    }

    /**
     * Saves the form of the settings page; $in holds the posted strings.
     * Another view loads its templates, another gallery its detail content and
     * image loop (news_img did the same). Templates from the form count only
     * in expert mode. "Admins only" can only be changed by admins.
     */
    public static function save(\Database $db, int $sectionId, array $in, bool $isAdmin): void
    {
        $old = self::get($db, $sectionId) ?? throw new \DomainException('ERR_SECTION');
        $int = static fn (string $key, int $min, int $max): int => max($min, min($max, (int) ($in[$key] ?? 0)));
        $flag = static fn (string $key): int => ($in[$key] ?? '') === '1' ? 1 : 0;

        $row = [
            'sort_order'   => $int('sort_order', 0, count(Articles::ORDER) - 1),
            'per_page'     => $int('per_page', 0, 999),
            'use_block2'   => $flag('use_block2'),
            'view'         => self::view((string) ($in['view'] ?? '')),
            'gallery'      => in_array($in['gallery'] ?? '', self::GALLERIES, true) ? $in['gallery'] : 'fotorama',
            'articles_dir' => (string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($in['articles_dir'] ?? '')),
            'expert_mode'  => $flag('expert_mode'),
            // MB in the form, bytes in the table; never above what PHP accepts
            'max_upload'   => min($int('max_upload', 1, 2048) * 1048576, ini_parse_quantity((string) ini_get('upload_max_filesize'))),
            'settings_admins_only' => $isAdmin ? $flag('settings_admins_only') : (int) $old['settings_admins_only'],
        ];
        foreach (['preview', 'thumb', 'large'] as $size) {
            $row[$size . '_width'] = $int($size . '_width', 1, 9999);
            $row[$size . '_height'] = $int($size . '_height', 1, 9999);
            $row[$size . '_crop'] = $flag($size . '_crop');
        }
        if ((int) $old['expert_mode'] === 1 && $row['expert_mode'] === 1) {
            foreach (self::VIEW_TEMPLATES as $column) {
                // browsers send CRLF from textareas
                $row[$column] = is_string($in[$column] ?? null) ? str_replace("\r\n", "\n", $in[$column]) : (string) $old[$column];
            }
        }
        if ($row['view'] !== $old['view']) {
            $row = self::viewTemplates($row['view']) + $row;
        }
        if ($row['gallery'] !== $old['gallery']) {
            $row = self::galleryTemplates($row['gallery']) + $row;
        }

        $set = implode(', ', array_map(static fn (string $column): string => "`$column` = ?", array_keys($row)));
        Articles::exec($db, "UPDATE `{TP}mod_articles_settings` SET $set WHERE `section_id` = ?", [...array_values($row), $sectionId]);
    }

    /** Detail content and image loop of js/galleries/<gallery>/settings.php, only the variables it sets. */
    public static function galleryTemplates(string $gallery): array
    {
        if (!in_array($gallery, self::GALLERIES, true)) {
            return [];
        }
        $vars = (static function (string $file): array {
            include $file;
            return get_defined_vars();
        })(dirname(__DIR__) . "/js/galleries/$gallery/settings.php");
        return array_filter([
            'tpl_detail_content' => $vars['post_content'] ?? null,
            'tpl_image_loop'     => $vars['image_loop'] ?? null,
        ], 'is_string');
    }

    /** Template columns from views/<view>/config.php; every column is set, missing variables become ''. */
    public static function viewTemplates(string $view): array
    {
        $vars = (static function (string $file): array {
            include $file;
            return get_defined_vars();
        })(dirname(__DIR__) . '/views/' . self::view($view) . '/config.php');

        $templates = [];
        foreach (self::VIEW_TEMPLATES as $var => $column) {
            $templates[$column] = (string) ($vars[$var] ?? '');
        }
        return $templates;
    }
}
