<?php
//:Lists articles of the module articles; counterpart of fetchNewsItems with the same parameters
//:[[articles_list?group_id_type=section_id&group_id=8&max_news_items=4]] Parameters: group_id_type (group_id, section_id, page_id, post_id), group_id, start_news_item, max_news_items, max_news_length, strip_tags, allowed_tags, sort_by (1 position, 2 last change, 3 published, 4 random, 5 end), sort_order (1 desc, 2 asc), not_older_than (days), lang_filter, lang_id, tags, skip, groups_on_tags, view, aslist
if (!is_file(WB_PATH . '/modules/articles/bootstrap.php')) {
    return '';
}
require_once WB_PATH . '/modules/articles/bootstrap.php';
return Articles\Api::list($GLOBALS['database'], get_defined_vars());
