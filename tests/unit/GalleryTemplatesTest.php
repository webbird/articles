<?php

declare(strict_types=1);

use Articles\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** A gallery swaps only the image block of the view's detail content. */
final class GalleryTemplatesTest extends TestCase
{
    public static function combinations(): array
    {
        $cases = [];
        foreach (Settings::VIEWS as $view) {
            foreach (Settings::GALLERIES as $gallery) {
                $cases["$view/$gallery"] = [$view, $gallery];
            }
        }
        return $cases;
    }

    #[DataProvider('combinations')]
    public function testViewKeepsItsLayout(string $view, string $gallery): void
    {
        $content = Settings::galleryTemplates($gallery, $view)['tpl_detail_content'];

        $this->assertStringContainsString('mod_articles_content_short', $content);
        $this->assertStringContainsString('[CONTENT_LONG]', $content);
        $this->assertStringNotContainsString('[CONTENT]', $content);
        $this->assertSame($gallery === 'none' ? 0 : 1, substr_count($content, '[IMAGES]'));
        if ($gallery !== 'fotorama') {
            $this->assertStringNotContainsString('class="fotorama"', $content);
        }
        $marker = ['fotorama' => 'class="fotorama"', 'masonry' => 'class="masonry-grid"', 'tobii' => 'class="articles-tobii"', 'none' => ''][$gallery];
        $this->assertStringContainsString($marker, $content);
    }

    public function testFotoramaOnItsOwnViewChangesNothing(): void
    {
        $this->assertSame(
            Settings::viewTemplates('default')['tpl_detail_content'],
            Settings::galleryTemplates('fotorama', 'default')['tpl_detail_content']
        );
    }

    public function testUnknownGalleryGivesNothing(): void
    {
        $this->assertSame([], Settings::galleryTemplates('lightcase', 'default'));
    }
}
