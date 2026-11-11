<?php
/**
 * articles – delete.php
 * Section removed: its articles, groups, tags, settings and image folders.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

Articles\Articles::deleteSection($database, (int) $section_id);
