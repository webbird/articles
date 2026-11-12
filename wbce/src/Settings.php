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
