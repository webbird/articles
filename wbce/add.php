<?php
/**
 * articles – add.php
 * New section: settings row with defaults.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

Articles\Settings::create($database, (int) $section_id);
