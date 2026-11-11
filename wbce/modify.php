<?php
/**
 * articles – modify.php
 * Backend of a section. Phase 4; until then a notice.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

echo '<p>', htmlspecialchars(Lang::get('MOD_ARTICLES', 'BACKEND_PENDING')), '</p>';
