<?php
/**
 * articles – Tags
 * Tags of a section (local or global) and their colors.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Tags
{
    /**
     * Tags available in a section: its own and the global ones, sorted naturally,
     * with the flag global and the number of articles of this section using them.
     *
     * @return list<array>
     */
    public static function forSection(\Database $db, int $sectionId): array
    {
        $tags = $db->fetchAll(
            'SELECT t.*, MIN(ts.`section_id`) = 0 AS `global`,
                    (SELECT COUNT(*) FROM `{TP}mod_articles_item_tags` it JOIN `{TP}mod_articles_items` i ON i.`article_id` = it.`article_id`
                      WHERE it.`tag_id` = t.`tag_id` AND i.`section_id` = ?) AS `articles`
               FROM `{TP}mod_articles_tags` t JOIN `{TP}mod_articles_tag_sections` ts ON ts.`tag_id` = t.`tag_id`
              WHERE ts.`section_id` IN (0, ?)
              GROUP BY t.`tag_id`, t.`title`, t.`color`, t.`text_color`',
            [$sectionId, $sectionId]
        );
        usort($tags, static fn (array $a, array $b): int => strnatcasecmp((string) $a['title'], (string) $b['title']));
        return $tags;
    }

    /**
     * Creates or updates a tag of a section; $global only by admins (the caller checks).
     * Throws DomainException with a language key.
     */
    public static function save(\Database $db, int $sectionId, int $tagId, string $title, string $color, string $textColor, bool $global): int
    {
        if ($title === '') {
            throw new \DomainException('ERR_TITLE_MISSING');
        }
        foreach (self::forSection($db, $sectionId) as $tag) {
            if ((int) $tag['tag_id'] !== $tagId && mb_strtolower((string) $tag['title']) === mb_strtolower($title)) {
                throw new \DomainException('ERR_TAG_EXISTS');
            }
        }
        $row = ['title' => $title, 'color' => self::safeColor($color), 'text_color' => self::safeColor($textColor)];
        $scope = $global ? 0 : $sectionId;

        return $db->transaction(static function (\Database $db) use ($tagId, $row, $scope, $sectionId): int {
            if ($tagId === 0) {
                $tagId = Articles::insert($db, 'tags', $row);
                Articles::exec($db, 'INSERT INTO `{TP}mod_articles_tag_sections` (`tag_id`, `section_id`) VALUES (?, ?)', [$tagId, $scope]);
                return $tagId;
            }
            Articles::exec($db, 'UPDATE `{TP}mod_articles_tags` SET `title` = ?, `color` = ?, `text_color` = ? WHERE `tag_id` = ?', [...array_values($row), $tagId]);
            $sections = array_map('intval', array_column(
                $db->fetchAll('SELECT `section_id` FROM `{TP}mod_articles_tag_sections` WHERE `tag_id` = ?', [$tagId]),
                'section_id'
            ));
            if ($sections !== [$scope]) {
                // Global back to local only while no other section uses it
                if ($scope !== 0 && (int) $db->fetchValue(
                    'SELECT COUNT(*) FROM `{TP}mod_articles_item_tags` it JOIN `{TP}mod_articles_items` i ON i.`article_id` = it.`article_id`
                      WHERE it.`tag_id` = ? AND i.`section_id` <> ?',
                    [$tagId, $sectionId]
                ) > 0) {
                    throw new \DomainException('ERR_TAG_USED_ELSEWHERE');
                }
                Articles::exec($db, 'DELETE FROM `{TP}mod_articles_tag_sections` WHERE `tag_id` = ?', [$tagId]);
                Articles::exec($db, 'INSERT INTO `{TP}mod_articles_tag_sections` (`tag_id`, `section_id`) VALUES (?, ?)', [$tagId, $scope]);
            }
            return $tagId;
        });
    }

    /** Removes the tag from all articles; their access files are not affected. */
    public static function delete(\Database $db, int $tagId): void
    {
        $db->transaction(static function (\Database $db) use ($tagId): void {
            foreach (['item_tags', 'tag_sections', 'tags'] as $table) {
                Articles::exec($db, "DELETE FROM `{TP}mod_articles_$table` WHERE `tag_id` = ?", [$tagId]);
            }
        });
    }

    /** @return list<int> the ids among $tagIds that are available in the section */
    public static function available(\Database $db, int $sectionId, array $tagIds): array
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds))));
        return $tagIds === [] ? [] : array_map('intval', array_column($db->fetchAll(
            'SELECT DISTINCT `tag_id` FROM `{TP}mod_articles_tag_sections` WHERE `section_id` IN (0, ?) AND `tag_id` IN ' . Articles::in($tagIds),
            [$sectionId]
        ), 'tag_id'));
    }

    /** Hex, rgb()/hsl() or a color keyword; anything else becomes '' (it ends up in a style attribute). */
    public static function safeColor(?string $color): string
    {
        $color = trim((string) $color);
        $patterns = [
            '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i',
            '/^(rgb|rgba|hsl|hsla)\(\s*[0-9,.\s%\/]+\)$/',
            '/^[a-z]{3,32}$/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $color)) {
                return $color;
            }
        }
        return '';
    }

    /** Black or white text for a hex background (YIQ brightness), '' for other color forms. */
    public static function contrastColor(string $color): string
    {
        $hex = ltrim(trim($color), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return '';
        }
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        return ($r * 299 + $g * 587 + $b * 114) / 1000 >= 140 ? '#000000' : '#ffffff';
    }

    /** ' style="…"' from color and text_color; without a text color it is picked for contrast. */
    public static function styleAttr(array $tag): string
    {
        $styles = [];
        $bg = self::safeColor($tag['color'] ?? '');
        if ($bg !== '') {
            $styles[] = 'background-color:' . $bg;
        }
        $fg = self::safeColor($tag['text_color'] ?? '');
        if ($fg === '' && $bg !== '') {
            $fg = self::contrastColor($bg);
        }
        if ($fg !== '') {
            $styles[] = 'color:' . $fg;
        }
        return $styles ? ' style="' . htmlspecialchars(implode(';', $styles), ENT_QUOTES | ENT_HTML5) . '"' : '';
    }
}
