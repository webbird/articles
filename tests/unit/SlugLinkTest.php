<?php

declare(strict_types=1);

use Articles\AccessFiles;
use Articles\Articles;
use Articles\Import;
use PHPUnit\Framework\TestCase;

defined('PAGE_SPACER') || define('PAGE_SPACER', '-');
defined('PAGES_DIRECTORY') || define('PAGES_DIRECTORY', '/pages');

if (!function_exists('page_filename')) {
    /** page_filename() of WBCE 1.7 without entities_to_7bit(), so only for ASCII input. */
    function page_filename($sStr)
    {
        $sStr = str_replace(['\'', '"', '`', '!', '@', '#', '$', '%', '^', '&', '*', '=', '+', '|', '/', '\\', ';', ':', ',', '?', '[', ']', '<', '>', '{', '}', '(', ')'], '', $sStr);
        $sStr = preg_replace(['/\.+/', '/\.+$/'], ['.', ''], $sStr);
        $sStr = strtolower((string) preg_replace('/(\s)+/', PAGE_SPACER, trim($sStr)));
        return str_replace(['%2F', '%'], ['/', ''], urlencode($sStr));
    }
}

if (!class_exists('Database', false)) {
    /** Answers the two queries of AccessFiles::dir(). */
    final class Database
    {
        public function __construct(public string $articlesDir = '', public string $pageLink = '') {}

        public function fetchValue(string $sql, array $params = []): string
        {
            return str_contains($sql, 'mod_articles_settings') ? $this->articlesDir : $this->pageLink;
        }
    }
}

final class SlugLinkTest extends TestCase
{
    public function testSlugCleansTheTitleAndFallsBack(): void
    {
        self::assertSame('hallo-welt', Articles::slug('Hallo   Welt!'));
        self::assertSame('was-nun', Articles::slug('  (Was nun?)  '));
        self::assertSame('v1.2', Articles::slug('-v1..2.-'));
        self::assertSame('article', Articles::slug('!?#'));
        self::assertSame('article', Articles::slug(''));
    }

    public function testSlugOfTakesTheStoredLinkOnlyWithItsOwnId(): void
    {
        self::assertSame('mein-text', Articles::slugOf(['link' => '/posts/mein-text-12', 'article_id' => 12, 'title' => 'Anders']));
        self::assertSame('jahr-2024', Articles::slugOf(['link' => '/a/b/jahr-2024-7', 'article_id' => 7, 'title' => 'Anders']));
        // copied or moved: the link still carries the old id, "-12" must not count as "-2"
        self::assertSame('neuer-titel', Articles::slugOf(['link' => '/posts/mein-text-12', 'article_id' => 2, 'title' => 'Neuer Titel']));
        self::assertSame('neuer-titel', Articles::slugOf(['link' => '', 'article_id' => 3, 'title' => 'Neuer Titel']));
    }

    public function testLinkAndSlugOfAreInverse(): void
    {
        $link = AccessFiles::link('aktuelles/archiv', 'mein-text', 42);

        self::assertSame('/aktuelles/archiv/mein-text-42', $link);
        self::assertSame('mein-text', Articles::slugOf(['link' => $link, 'article_id' => 42, 'title' => '']));
    }

    public function testImportSlugKeepsTheSourceNameWithoutItsId(): void
    {
        self::assertSame('alter-name', Import::slug('/posts/alter-name-7', 7, 'Titel'));
        self::assertSame('foo-17', Import::slug('/posts/foo-17', 7, 'Titel'));
        self::assertSame('neuer-titel', Import::slug('', 7, 'Neuer Titel'));
        self::assertSame('article', Import::slug('', 7, '!!!'));
    }

    public function testDirAllowsOnlySegmentCharacters(): void
    {
        self::assertSame('meinordnerx', AccessFiles::dir(new Database('mein ordner/../x', '/egal'), 1, 1));
        self::assertSame('eltern/kind/seite', AccessFiles::dir(new Database('', '/eltern/../kind//seite/'), 1, 1));
        self::assertSame('posts', AccessFiles::dir(new Database('', '/../..'), 1, 1));
        self::assertSame('posts', AccessFiles::dir(new Database(), 1, 1));
    }

    public function testAccessFileClimbsToTheRoot(): void
    {
        self::assertStringContainsString("require __DIR__ . '/../../index.php';", AccessFiles::content('/posts/a-1', 1, 2, 3));
        self::assertStringContainsString("require __DIR__ . '/../../../../index.php';", AccessFiles::content('/a/b/c/d-1', 1, 2, 3));
        self::assertStringContainsString("define('ARTICLE_ID', 1);\ndefine('ARTICLE_SECTION', 2);", AccessFiles::content('/posts/a-1', 1, 2, 3));
    }
}
