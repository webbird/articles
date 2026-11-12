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
