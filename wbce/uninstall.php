<?php
/**
 * articles – uninstall.php
 * Drops the tables and the media folder .articles. WBCE only calls it when no section uses the module.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

Articles\Schema::uninstall($database);
