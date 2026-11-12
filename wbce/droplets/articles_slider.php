<?php
//:Image slider from the articles of a section (default: Bootstrap 5 carousel, needs Bootstrap 5 in the template)
//:[[articles_slider?section_id=8]] Optional: tpl=<name of a file in modules/articles/templates/slides/ without .txt>, default bs5carousel
if (!is_file(WB_PATH . '/modules/articles/bootstrap.php')) {
    return '';
}
require_once WB_PATH . '/modules/articles/bootstrap.php';
return Articles\Api::slider($GLOBALS['database'], (int) ($section_id ?? 0), (string) ($tpl ?? 'bs5carousel'));
