-- articles: tables. Indexes are added in Schema::install() via addIndex(),
-- because importSql() drops inline KEY clauses on SQLite.

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_settings` (
  `section_id`           INT          NOT NULL DEFAULT 0,
  `tpl_list_header`      TEXT         NOT NULL,
  `tpl_list_item`        TEXT         NOT NULL,
  `tpl_list_footer`      TEXT         NOT NULL,
  `tpl_detail_header`    TEXT         NOT NULL,
  `tpl_detail_content`   TEXT         NOT NULL,
  `tpl_detail_footer`    TEXT         NOT NULL,
  `tpl_image_loop`       TEXT         NOT NULL,
  `tpl_block2`           TEXT         NOT NULL,
  `sort_order`           INT          NOT NULL DEFAULT 0,
  `per_page`             INT          NOT NULL DEFAULT 0,
  `preview_width`        INT          NOT NULL DEFAULT 0,
  `preview_height`       INT          NOT NULL DEFAULT 0,
  `preview_crop`         TINYINT(1)   NOT NULL DEFAULT 0,
  `thumb_width`          INT          NOT NULL DEFAULT 0,
  `thumb_height`         INT          NOT NULL DEFAULT 0,
  `thumb_crop`           TINYINT(1)   NOT NULL DEFAULT 0,
  `large_width`          INT          NOT NULL DEFAULT 0,
  `large_height`         INT          NOT NULL DEFAULT 0,
  `large_crop`           TINYINT(1)   NOT NULL DEFAULT 0,
  `max_upload`           INT          NOT NULL DEFAULT 0,
  `use_block2`           TINYINT(1)   NOT NULL DEFAULT 0,
  `view`                 VARCHAR(50)  NOT NULL DEFAULT 'default',
  `gallery`              VARCHAR(50)  NOT NULL DEFAULT 'fotorama',
  `articles_dir`         VARCHAR(64)  NOT NULL DEFAULT '',
  `default_image`        VARCHAR(255) NOT NULL DEFAULT '',
  `expert_mode`          TINYINT(1)   NOT NULL DEFAULT 0,
  `settings_admins_only` TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`section_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_items` (
  `article_id`      INT          NOT NULL AUTO_INCREMENT,
  `section_id`      INT          NOT NULL DEFAULT 0,
  `group_id`        INT          NOT NULL DEFAULT 0,
  `position`        INT          NOT NULL DEFAULT 0,
  `active`          TINYINT(1)   NOT NULL DEFAULT 0,
  `title`           VARCHAR(255) NOT NULL DEFAULT '',
  `link`            VARCHAR(255) NOT NULL DEFAULT '',
  `image`           VARCHAR(255) NOT NULL DEFAULT '',
  `teaser`          TEXT         NOT NULL,
  `content`         MEDIUMTEXT   NOT NULL,
  `block2`          TEXT         NOT NULL,
  `published_from`  INT          NOT NULL DEFAULT 0,
  `published_until` INT          NOT NULL DEFAULT 0,
  `created_at`      INT          NOT NULL DEFAULT 0,
  `created_by`      INT          NOT NULL DEFAULT 0,
  `modified_at`     INT          NOT NULL DEFAULT 0,
  `modified_by`     INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`article_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_groups` (
  `group_id`   INT          NOT NULL AUTO_INCREMENT,
  `section_id` INT          NOT NULL DEFAULT 0,
  `position`   INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 0,
  `title`      VARCHAR(255) NOT NULL DEFAULT '',
  `image`      VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`group_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_images` (
  `image_id`   INT          NOT NULL AUTO_INCREMENT,
  `article_id` INT          NOT NULL DEFAULT 0,
  `file`       VARCHAR(255) NOT NULL DEFAULT '',
  `caption`    VARCHAR(255) NOT NULL DEFAULT '',
  `position`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`image_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_tags` (
  `tag_id`     INT          NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL DEFAULT '',
  `color`      VARCHAR(7)   NULL DEFAULT NULL,
  `text_color` VARCHAR(7)   NULL DEFAULT NULL,
  PRIMARY KEY (`tag_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

-- section_id 0 = global tag
CREATE TABLE IF NOT EXISTS `{TP}mod_articles_tag_sections` (
  `tag_id`     INT NOT NULL,
  `section_id` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`tag_id`, `section_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

CREATE TABLE IF NOT EXISTS `{TP}mod_articles_item_tags` (
  `article_id` INT NOT NULL,
  `tag_id`     INT NOT NULL,
  PRIMARY KEY (`article_id`, `tag_id`)
){TABLE_ENGINE}{TABLE_COLLATION};

-- Origin of imported articles, basis for the later redirect/link rewrite step
CREATE TABLE IF NOT EXISTS `{TP}mod_articles_import` (
  `article_id`        INT         NOT NULL,
  `source_module`     VARCHAR(50) NOT NULL DEFAULT '',
  `source_section_id` INT         NOT NULL DEFAULT 0,
  `source_id`         INT         NOT NULL DEFAULT 0,
  `imported_at`       INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (`article_id`)
){TABLE_ENGINE}{TABLE_COLLATION};
