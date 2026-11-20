<?php
/**
 * articles – upgrade.php
 * Creates missing tables and indexes and imports the droplets; safe to repeat.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

Articles\Schema::install($database);
Articles\Schema::installDroplets($database);

// 0.2.0 dropped Lightcase: such sections get Tobii and its templates, as a gallery change in the settings would
foreach ($database->fetchAll("SELECT `section_id`, `view` FROM `{TP}mod_articles_settings` WHERE `gallery` = 'lightcase'") as $row) {
    $tobii = Articles\Settings::galleryTemplates('tobii', (string) $row['view']);
    Articles\Articles::exec(
        $database,
        'UPDATE `{TP}mod_articles_settings` SET `gallery` = ?, `tpl_detail_content` = ?, `tpl_image_loop` = ? WHERE `section_id` = ?',
        ['tobii', $tobii['tpl_detail_content'], $tobii['tpl_image_loop'], (int) $row['section_id']]
    );
}
