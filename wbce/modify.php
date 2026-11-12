<?php
/**
 * articles – modify.php
 * Backend of a section. Runs inside the section loop of admin/pages/modify.php
 * and shares its variable scope; everything happens in Articles\Backend.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

echo Articles\Backend::render($database, $admin, (int) $page_id, (int) $section_id);
