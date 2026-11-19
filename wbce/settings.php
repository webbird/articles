<?php
/**
 * articles – settings.php
 * Settings of a section on a page of their own, without the other sections of
 * the page that admin/pages/modify.php would show below. Saving goes to backend.php.
 * article.php uses it for the article editor.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

// SCRIPT_FILENAME keeps the junction path during development; __DIR__ would resolve it
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';

require WB_PATH . '/modules/admin.php';
require_once __DIR__ . '/bootstrap.php';

// admin.php checks the page permissions, but not that the section belongs to that page
if (!isset($section) || (int) $section['page_id'] !== (int) $page_id || $section['module'] !== 'articles') {
    $admin->print_error($MESSAGE['GENERIC_SECURITY_ACCESS'], ADMIN_URL . '/pages/modify.php?page_id=' . (int) $page_id);
}

// Backend::render() takes its view from art_section/art_view, as in modify.php
$_GET['art_section'] = (int) $section_id;
$_GET['art_view'] = $artView ?? 'settings';   // article.php sets 'edit'
// the wrapper of pages_modify.htt, which the backend themes style
echo '<div class="pageModuleWrapper articles">',
    Articles\Backend::render($database, $admin, (int) $page_id, (int) $section_id),
    '</div>';

$admin->print_footer();
