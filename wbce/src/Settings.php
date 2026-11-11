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
    /** Values of a new section; image sizes as in news_img's add.php. */
    public const DEFAULTS = [
        // shortcut: templates stay empty until the views arrive in Phase 3, then they come from views/default
        'tpl_list_header'      => '',
        'tpl_list_item'        => '',
        'tpl_list_footer'      => '',
        'tpl_detail_header'    => '',
        'tpl_detail_content'   => '',
        'tpl_detail_footer'    => '',
        'tpl_image_loop'       => '',
        'tpl_block2'           => '',
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
            + ['max_upload' => ini_parse_quantity((string) ini_get('upload_max_filesize'))]);
        if ($result !== true) {
            throw new \RuntimeException('articles: settings for section ' . $sectionId . ': ' . $result);
        }
    }
}
