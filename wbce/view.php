<?php
/**
 * articles – view.php
 * Frontend of a section: the detail page when an access file of this section
 * set ARTICLE_ID, otherwise the list.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

$articlesFrontend = Articles\Frontend::forSection($database, (int) $section_id);
if ($articlesFrontend !== null) {
    // Other articles sections on the same page keep showing their list.
    echo defined('ARTICLE_ID') && (int) ARTICLE_SECTION === (int) $section_id
        ? $articlesFrontend->detail((int) ARTICLE_ID, $_GET)
        : $articlesFrontend->list($_GET);
}
