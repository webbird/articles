<?php
/**
 * articles – Articles
 * Visibility, order, saving, copying, moving and deleting of articles, and
 * removing everything that belongs to a section.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Articles
{
    /** Setting sort_order => column and direction; the six orders of news_img (3 was posted_when). */
    public const ORDER = [
        0 => ['position', 'DESC'],
        1 => ['published_from', 'DESC'],
        2 => ['published_until', 'DESC'],
        3 => ['modified_at', 'DESC'],
        4 => ['article_id', 'DESC'],
        5 => ['published_until', 'ASC'],
    ];

    /**
     * The one visibility rule for list, count, detail, neighbours, RSS, search,
     * droplets and access files: active, has a title, inside its period, group
     * active or no group. $alias is the items table. $orPlanned also lets
     * through articles that become visible later (for the link picker).
     */
    public static function visibleSql(string $alias = 'i', ?int $now = null, bool $orPlanned = false): string
    {
        $now ??= time();
        $a = '`' . $alias . '`';
        return "$a.`active` = 1 AND $a.`title` <> ''"
            . ($orPlanned ? '' : " AND ($a.`published_from` = 0 OR $a.`published_from` <= $now)")
            . " AND ($a.`published_until` = 0 OR $a.`published_until` >= $now)"
            . " AND ($a.`group_id` = 0 OR EXISTS (SELECT 1 FROM `{TP}mod_articles_groups` gv"
            . " WHERE gv.`group_id` = $a.`group_id` AND gv.`active` = 1))";
    }

    /**
     * For output outside the section's own page (droplets): the page is public
     * or hidden and the section inside its period. Pages for registered users
     * stay out, even for visitors who could see them. $s is sections, $p is pages.
     */
    public static function publicSql(string $s = 's', string $p = 'p', ?int $now = null): string
    {
        return "`$p`.`visibility` IN ('public', 'hidden') AND " . self::periodSql($s, $now);
    }

    /** The section is inside its publication period. $s is sections. */
    public static function periodSql(string $s = 's', ?int $now = null): string
    {
        $now ??= time();
        return "(`$s`.`publ_start` = 0 OR `$s`.`publ_start` <= $now)"
            . " AND (`$s`.`publ_end` = 0 OR `$s`.`publ_end` >= $now)";
    }

    /** ORDER BY for a sort_order; article_id breaks ties so pages and neighbours are stable. */
    public static function orderSql(int $sortOrder, string $alias = 'i'): string
    {
        [$column, $direction] = self::ORDER[$sortOrder] ?? self::ORDER[0];
        return "`$alias`.`$column` $direction, `$alias`.`article_id` $direction";
    }

    /** Why an article is shown or not; the same rule as visibleSql(), for the backend list. */
    public static function status(array $article, bool $groupActive, int $now): string
    {
        return match (true) {
            !(int) $article['active'] => 'inactive',
            (int) $article['published_until'] !== 0 && (int) $article['published_until'] < $now => 'expired',
            (int) $article['published_from'] > $now => 'planned',
            !$groupActive => 'hidden',
            default => 'visible',
        };
    }

    public static function slug(string $text): string
    {
        $slug = trim((string) page_filename($text), '-_.');
        return $slug !== '' ? $slug : 'article';
    }

    /** The slug inside the stored link: its basename without PAGE_SPACER and id. */
    public static function slugOf(array $article): string
    {
        $base = basename((string) $article['link']);
        $suffix = PAGE_SPACER . $article['article_id'];
        return $base !== '' && str_ends_with($base, $suffix) ? substr($base, 0, -strlen($suffix)) : self::slug((string) $article['title']);
    }

    public static function pageOf(\Database $db, int $sectionId): int
    {
        return (int) $db->fetchValue('SELECT `page_id` FROM `{TP}sections` WHERE `section_id` = ?', [$sectionId]);
    }

    /**
     * Creates (id 0) or updates an article from validated values: id, section_id,
     * group_id, title, slug, active, published_from, published_until, teaser,
     * content, block2, tags (ids available in the section). Returns the id.
     */
    public static function save(\Database $db, array $v, int $userId): int
    {
        $now = time();
        $sectionId = (int) $v['section_id'];
        $old = $v['id'] ? $db->fetchRow('SELECT * FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [(int) $v['id']]) : null;
        $dir = AccessFiles::dir($db, $sectionId, self::pageOf($db, $sectionId));

        $id = $db->transaction(static function (\Database $db) use ($v, $old, $sectionId, $dir, $now, $userId): int {
            $row = [
                'section_id'      => $sectionId,
                'group_id'        => (int) $v['group_id'],
                'title'           => (string) $v['title'],
                'active'          => $v['active'] ? 1 : 0,
                'teaser'          => (string) $v['teaser'],
                'content'         => (string) $v['content'],
                'block2'          => (string) $v['block2'],
                'published_from'  => (int) $v['published_from'],
                'published_until' => (int) $v['published_until'],
                'modified_at'     => $now,
                'modified_by'     => $userId,
            ];
            if ($old === null) {
                $id = self::insert($db, 'items', $row + [
                    'position' => self::nextPosition($db, $sectionId), 'link' => '', 'image' => '', 'created_at' => $now, 'created_by' => $userId,
                ]);
            } else {
                $id = (int) $old['article_id'];
                if ((int) $old['section_id'] !== $sectionId) {
                    $row['position'] = self::nextPosition($db, $sectionId);
                }
                self::update($db, $id, $row);
            }
            self::update($db, $id, ['link' => AccessFiles::link($dir, (string) $v['slug'], $id)]);
            self::setTagsOf($db, $id, $v['tags']);
            return $id;
        });

        $link = (string) $db->fetchValue('SELECT `link` FROM `{TP}mod_articles_items` WHERE `article_id` = ?', [$id]);
        if ($old !== null && $old['link'] !== '' && $old['link'] !== $link) {
            AccessFiles::remove((string) $old['link']);
        }
        AccessFiles::sync($db, $id);
        return $id;
    }

    /** Rows, images, tags, origin, image folders and access files of the articles. */
    public static function delete(\Database $db, array $ids): void
    {
        $in = self::in($ids);
        $links = array_column($db->fetchAll("SELECT `link` FROM `{TP}mod_articles_items` WHERE `article_id` IN $in"), 'link');
        $db->transaction(static function (\Database $db) use ($in): void {
            foreach (['images', 'item_tags', 'import', 'items'] as $table) {
                self::exec($db, "DELETE FROM `{TP}mod_articles_$table` WHERE `article_id` IN $in");
            }
        });
        foreach ($links as $link) {
            AccessFiles::remove((string) $link);
        }
        foreach ($ids as $id) {
            if (is_dir(\ARTICLES_MEDIA_PATH . '/items/' . (int) $id)) {
                rm_full_dir(\ARTICLES_MEDIA_PATH . '/items/' . (int) $id);
            }
        }
    }

    /**
     * Sets one column for all articles and syncs their access files. Only for
     * active, group_id, published_from and published_until; values are validated by the caller.
     */
    public static function set(\Database $db, array $ids, string $column, int $value): void
    {
        if (!in_array($column, ['active', 'group_id', 'published_from', 'published_until'], true)) {
            throw new \InvalidArgumentException("articles: column $column");
        }
        self::exec($db, "UPDATE `{TP}mod_articles_items` SET `$column` = ? WHERE `article_id` IN " . self::in($ids), [$value]);
        foreach ($ids as $id) {
            AccessFiles::sync($db, (int) $id);
        }
    }

    /** Replaces the tags of the articles; the tag ids must be available in their section. */
    public static function setTags(\Database $db, array $ids, array $tagIds): void
    {
        $db->transaction(static function (\Database $db) use ($ids, $tagIds): void {
            foreach ($ids as $id) {
                self::setTagsOf($db, (int) $id, $tagIds);
            }
        });
    }

    /** New order of a section, top first (positions run downwards, the list sorts DESC). */
    public static function reorder(\Database $db, int $sectionId, array $ids): void
    {
        $db->transaction(static function (\Database $db) use ($sectionId, $ids): void {
            $position = count($ids);
            foreach ($ids as $id) {
                self::exec($db, 'UPDATE `{TP}mod_articles_items` SET `position` = ? WHERE `article_id` = ? AND `section_id` = ?', [$position--, (int) $id, $sectionId]);
            }
        });
    }

    /** One step up or down in the custom order; renumbers the section, imports may share positions. */
    public static function step(\Database $db, int $sectionId, int $id, bool $up): void
    {
        $ids = array_map('intval', array_column($db->fetchAll(
            'SELECT i.`article_id` FROM `{TP}mod_articles_items` i WHERE i.`section_id` = ? ORDER BY ' . self::orderSql(0),
            [$sectionId]
        ), 'article_id'));
        if ($ids = self::swap($ids, $id, $up)) {
            self::reorder($db, $sectionId, $ids);
        }
    }

    /**
     * The ids with $id swapped with its neighbour above or below; null if
     * $id is missing or already first or last. For the arrows of articles, groups and images.
     *
     * @param list<int> $ids
     * @return list<int>|null
     */
    public static function swap(array $ids, int $id, bool $up): ?array
    {
        $pos = array_search($id, $ids, true);
        $other = is_int($pos) ? $pos + ($up ? -1 : 1) : -1;
        if (!isset($ids[$other])) {
            return null;
        }
        [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];
        return $ids;
    }

    /**
     * Copies into $target (may be the same section). Copies are inactive and on
     * top, keep their group only inside the same section and get their own image folder.
     */
    public static function copy(\Database $db, array $ids, int $target, bool $withTags, int $userId): void
    {
        $dir = AccessFiles::dir($db, $target, self::pageOf($db, $target));
        $now = time();
        $copies = $db->transaction(static function (\Database $db) use ($ids, $target, $withTags, $userId, $dir, $now): array {
            $copies = [];
            foreach (self::rows($db, $ids) as $a) {
                $old = (int) $a['article_id'];
                unset($a['article_id']);
                $new = self::insert($db, 'items', [
                    'section_id'  => $target,
                    'group_id'    => (int) $a['section_id'] === $target ? (int) $a['group_id'] : 0,
                    'position'    => self::nextPosition($db, $target),
                    'active'      => 0,
                    'created_at'  => $now,
                    'created_by'  => $userId,
                    'modified_at' => $now,
                    'modified_by' => $userId,
                ] + $a);
                self::update($db, $new, ['link' => AccessFiles::link($dir, self::slugOf($a + ['article_id' => $old]), $new)]);
                self::exec($db, 'INSERT INTO `{TP}mod_articles_images` (`article_id`, `file`, `caption`, `position`)'
                    . ' SELECT ?, `file`, `caption`, `position` FROM `{TP}mod_articles_images` WHERE `article_id` = ?', [$new, $old]);
                if ($withTags) {
                    self::setTagsOf($db, $new, self::mapTags($db, self::tagIdsOf($db, $old), $target));
                }
                $copies[$old] = $new;
            }
            return $copies;
        });
        foreach ($copies as $old => $new) {
            self::copyDir(\ARTICLES_MEDIA_PATH . "/items/$old", \ARTICLES_MEDIA_PATH . "/items/$new");
        }
    }

    /** Moves into another section: on top, without group, link in the target's directory. */
    public static function move(\Database $db, array $ids, int $target, bool $withTags): void
    {
        $dir = AccessFiles::dir($db, $target, self::pageOf($db, $target));
        $oldLinks = $db->transaction(static function (\Database $db) use ($ids, $target, $withTags, $dir): array {
            $oldLinks = [];
            foreach (self::rows($db, $ids) as $a) {
                $id = (int) $a['article_id'];
                if ((int) $a['section_id'] === $target) {
                    continue;
                }
                $oldLinks[$id] = (string) $a['link'];
                $tags = $withTags ? self::mapTags($db, self::tagIdsOf($db, $id), $target) : [];
                self::update($db, $id, [
                    'section_id' => $target,
                    'group_id'   => 0,
                    'position'   => self::nextPosition($db, $target),
                    'link'       => AccessFiles::link($dir, self::slugOf($a), $id),
                ]);
                self::setTagsOf($db, $id, $tags);
            }
            return $oldLinks;
        });
        foreach ($oldLinks as $id => $link) {
            AccessFiles::remove($link);
            AccessFiles::sync($db, $id);
        }
    }

    /**
     * Links of the section in the directory it uses now, e.g. after changing
     * articles_dir. Old addresses stop working. Returns how many moved.
     */
    public static function relink(\Database $db, int $sectionId): int
    {
        $moved = [];
        foreach (self::outsideDir($db, $sectionId) as $id => [$old, $new]) {
            self::update($db, $id, ['link' => $new]);
            $moved[$id] = $old;
        }
        foreach ($moved as $id => $old) {
            AccessFiles::remove($old);
            AccessFiles::sync($db, $id);
        }
        return count($moved);
    }

    /** @return array<int, array{string, string}> article id => old link, link in the current directory */
    public static function outsideDir(\Database $db, int $sectionId): array
    {
        $dir = AccessFiles::dir($db, $sectionId, self::pageOf($db, $sectionId));
        $outside = [];
        foreach ($db->fetchAll('SELECT `article_id`, `title`, `link` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]) as $a) {
            $link = AccessFiles::link($dir, self::slugOf($a), (int) $a['article_id']);
            if ($link !== (string) $a['link']) {
                $outside[(int) $a['article_id']] = [(string) $a['link'], $link];
            }
        }
        return $outside;
    }

    /** @return list<int> */
    public static function tagIdsOf(\Database $db, int $articleId): array
    {
        return array_map('intval', array_column(
            $db->fetchAll('SELECT `tag_id` FROM `{TP}mod_articles_item_tags` WHERE `article_id` = ?', [$articleId]),
            'tag_id'
        ));
    }

    public static function exec(\Database $db, string $sql, array $params = []): void
    {
        $db->query($sql, $params);
        // transaction() only sees the error of the last query, so check each one
        if ($db->hasError()) {
            throw new \RuntimeException('articles: ' . $db->getError());
        }
    }

    /** Inserts into mod_articles_<table> and returns the new id. */
    public static function insert(\Database $db, string $table, array $row): int
    {
        $result = $db->insertRow("{TP}mod_articles_$table", $row);
        if ($result !== true) {
            throw new \RuntimeException("articles: insert into $table: " . (is_string($result) ? $result : $db->getError()));
        }
        return (int) $db->lastInsertId();
    }

    /** "(1,2,3)" from ids; ints only, so it is safe inside SQL. */
    public static function in(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return '(' . ($ids === [] ? '0' : implode(',', $ids)) . ')';
    }

    private static function update(\Database $db, int $id, array $row): void
    {
        $set = implode(', ', array_map(static fn (string $column): string => "`$column` = ?", array_keys($row)));
        self::exec($db, "UPDATE `{TP}mod_articles_items` SET $set WHERE `article_id` = ?", [...array_values($row), $id]);
    }

    /** Rows in list order bottom first, so copies and moved articles keep their order on top of the target. */
    private static function rows(\Database $db, array $ids): array
    {
        return $db->fetchAll('SELECT i.* FROM `{TP}mod_articles_items` i WHERE i.`article_id` IN ' . self::in($ids)
            . ' ORDER BY i.`position` ASC, i.`article_id` ASC');
    }

    private static function nextPosition(\Database $db, int $sectionId): int
    {
        return (int) $db->fetchValue('SELECT MAX(`position`) FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]) + 1;
    }

    private static function setTagsOf(\Database $db, int $articleId, array $tagIds): void
    {
        self::exec($db, 'DELETE FROM `{TP}mod_articles_item_tags` WHERE `article_id` = ?', [$articleId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            self::exec($db, 'INSERT INTO `{TP}mod_articles_item_tags` (`article_id`, `tag_id`) VALUES (?, ?)', [$articleId, $tagId]);
        }
    }

    /**
     * Tags for another section: global ones and those of the target stay, local
     * ones become the target's tag of the same title, created if missing.
     *
     * @return list<int>
     */
    public static function mapTags(\Database $db, array $tagIds, int $target): array
    {
        $mapped = [];
        foreach ($tagIds as $tagId) {
            if ((int) $db->fetchValue(
                'SELECT COUNT(*) FROM `{TP}mod_articles_tag_sections` WHERE `tag_id` = ? AND `section_id` IN (0, ?)',
                [$tagId, $target]
            ) > 0) {
                $mapped[] = $tagId;
                continue;
            }
            $tag = $db->fetchRow('SELECT `title`, `color`, `text_color` FROM `{TP}mod_articles_tags` WHERE `tag_id` = ?', [$tagId]);
            if ($tag === null) {
                continue;
            }
            $same = (int) $db->fetchValue(
                'SELECT t.`tag_id` FROM `{TP}mod_articles_tags` t JOIN `{TP}mod_articles_tag_sections` ts ON ts.`tag_id` = t.`tag_id`
                  WHERE ts.`section_id` IN (0, ?) AND LOWER(t.`title`) = LOWER(?)',
                [$target, $tag['title']]
            );
            if ($same === 0) {
                $same = self::insert($db, 'tags', $tag);
                self::exec($db, 'INSERT INTO `{TP}mod_articles_tag_sections` (`tag_id`, `section_id`) VALUES (?, ?)', [$same, $target]);
            }
            $mapped[] = $same;
        }
        return $mapped;
    }

    public static function copyDir(string $from, string $to): void
    {
        if (!is_dir($from)) {
            return;
        }
        is_dir($to) || mkdir($to, 0755, true);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file) {
            $dest = $to . '/' . $files->getSubPathname();
            $file->isDir() ? (is_dir($dest) || mkdir($dest, 0755, true)) : copy($file->getPathname(), $dest);
        }
    }

    /**
     * Deletes all rows of a section, tags no section uses any more (news_img
     * left them behind) and the section's image folders.
     */
    public static function deleteSection(\Database $db, int $sectionId): void
    {
        $articles = $db->fetchAll('SELECT `article_id`, `link` FROM `{TP}mod_articles_items` WHERE `section_id` = ?', [$sectionId]);
        $articleIds = array_map('intval', array_column($articles, 'article_id'));
        $links = array_column($articles, 'link');
        $groupIds = array_map('intval', array_column(
            $db->fetchAll('SELECT `group_id` FROM `{TP}mod_articles_groups` WHERE `section_id` = ?', [$sectionId]),
            'group_id'
        ));

        $db->transaction(static function (\Database $db) use ($sectionId): void {
            $ofSection = 'IN (SELECT `article_id` FROM `{TP}mod_articles_items` WHERE `section_id` = ?)';
            foreach ([
                "DELETE FROM `{TP}mod_articles_images` WHERE `article_id` $ofSection",
                "DELETE FROM `{TP}mod_articles_item_tags` WHERE `article_id` $ofSection",
                "DELETE FROM `{TP}mod_articles_import` WHERE `article_id` $ofSection",
                'DELETE FROM `{TP}mod_articles_items` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_groups` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_tag_sections` WHERE `section_id` = ?',
                'DELETE FROM `{TP}mod_articles_settings` WHERE `section_id` = ?',
                // Global tags keep their section 0 row and survive.
                'DELETE FROM `{TP}mod_articles_tags` WHERE `tag_id` NOT IN (SELECT `tag_id` FROM `{TP}mod_articles_tag_sections`)',
            ] as $sql) {
                self::exec($db, $sql, str_contains($sql, '?') ? [$sectionId] : []);
            }
        });

        // Files after the commit: a failed transaction must not cost any images.
        foreach ($links as $link) {
            AccessFiles::remove($link);
        }
        $folders = array_merge(
            array_map(static fn (int $id): string => "/items/$id", $articleIds),
            array_map(static fn (int $id): string => "/groups/$id", $groupIds),
            ["/sections/$sectionId"]
        );
        foreach ($folders as $folder) {
            if (is_dir(\ARTICLES_MEDIA_PATH . $folder)) {
                rm_full_dir(\ARTICLES_MEDIA_PATH . $folder);
            }
        }
    }
}
