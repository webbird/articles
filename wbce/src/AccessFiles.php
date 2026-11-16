<?php
/**
 * articles – AccessFiles
 * Access files of the articles below the pages directory: path, writing, removing.
 * Moving them to another directory: Articles::relink() (maintenance tab).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class AccessFiles
{
    /**
     * Directory below PAGES_DIRECTORY, without slashes at the ends: the setting
     * articles_dir, else the link of the page, else "posts" (as news_img v6.0).
     */
    public static function dir(\Database $db, int $sectionId, int $pageId): string
    {
        $override = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $db->fetchValue(
            'SELECT `articles_dir` FROM `{TP}mod_articles_settings` WHERE `section_id` = ?',
            [$sectionId]
        ));
        if ($override !== '') {
            return $override;
        }
        // Segment characters only, so ".." cannot survive.
        $link = (string) preg_replace('#[^a-zA-Z0-9_/-]+#', '', (string) $db->fetchValue(
            'SELECT `link` FROM `{TP}pages` WHERE `page_id` = ?',
            [$pageId]
        ));
        $link = trim((string) preg_replace('#/+#', '/', $link), '/');

        return $link !== '' ? $link : 'posts';
    }

    /** Link as stored in mod_articles_items, without PAGES_DIRECTORY and extension. */
    public static function link(string $dir, string $slug, int $articleId): string
    {
        return '/' . $dir . '/' . $slug . PAGE_SPACER . $articleId;
    }

    public static function path(string $link): string
    {
        return WB_PATH . PAGES_DIRECTORY . $link . PAGE_EXTENSION;
    }

    /**
     * True if the file is missing or was written by articles. news_img and
     * articles on one page derive the same directory, so a foreign file must
     * never be overwritten or removed.
     */
    public static function isOurs(string $file): bool
    {
        return !is_file($file) || str_contains((string) file_get_contents($file), "define('ARTICLE_ID'");
    }

    /**
     * File content; the require climbs from the file's folder to the WBCE root.
     * $article_id is read as a global by modules such as simplepagehead (title and description in the head).
     */
    public static function content(string $link, int $articleId, int $sectionId, int $pageId): string
    {
        $up = str_repeat('/..', substr_count($link, '/') - 1 + substr_count(PAGES_DIRECTORY, '/'));
        return "<?php\n"
            . "// articles: access file of an article, generated, do not edit\n"
            . "\$page_id = $pageId;\n"
            . "\$section_id = $sectionId;\n"
            . "\$article_id = $articleId;\n"
            . "define('ARTICLE_ID', $articleId);\n"
            . "define('ARTICLE_SECTION', $sectionId);\n"
            . "require __DIR__ . '$up/index.php';\n";
    }

    /** Writes the file if it is missing or outdated. False if it belongs to someone else or cannot be written. */
    public static function write(array $article, int $pageId): bool
    {
        $link = (string) $article['link'];
        if (!preg_match('#^(/[a-zA-Z0-9_.-]+)+$#', $link) || str_contains($link, '..')) {
            return false;
        }
        $file = self::path($link);
        if (!self::isOurs($file)) {
            return false;
        }
        $content = self::content($link, (int) $article['article_id'], (int) $article['section_id'], $pageId);
        if (is_file($file) && file_get_contents($file) === $content) {
            return true;
        }
        if (!is_dir(dirname($file)) && !make_dir(dirname($file))) {
            return false;
        }
        if (file_put_contents($file, $content, LOCK_EX) !== strlen($content)) {
            return false;
        }
        change_mode($file);
        return true;
    }

    public static function remove(string $link): void
    {
        $file = self::path($link);
        if (is_file($file) && self::isOurs($file)) {
            unlink($file);
        }
    }

    /**
     * After every write: the file exists exactly while the article is visible.
     * Uses Articles::visibleSql(), so the frontend and the files agree.
     */
    public static function sync(\Database $db, int $articleId): void
    {
        $row = $db->fetchRow(
            'SELECT i.`article_id`, i.`section_id`, i.`link`, s.`page_id`, (' . Articles::visibleSql('i') . ') AS `visible`
               FROM `{TP}mod_articles_items` i JOIN `{TP}sections` s ON s.`section_id` = i.`section_id`
              WHERE i.`article_id` = ?',
            [$articleId]
        );
        if ($row === null) {
            return;
        }
        if ((int) $row['visible'] === 1) {
            self::write($row, (int) $row['page_id']);
        } else {
            self::remove((string) $row['link']);
        }
    }
}
