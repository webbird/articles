<?php

declare(strict_types=1);

use Articles\Frontend;
use Articles\Placeholders;
use Articles\Tags;
use PHPUnit\Framework\TestCase;

final class RenderingTest extends TestCase
{
    public function testKnownPlaceholdersAreReplacedOthersStay(): void
    {
        $this->assertSame(
            '<h2>Titel</h2> [UNKNOWN] ',
            Placeholders::render('<h2>[TITLE]</h2> [UNKNOWN] [CONTENT_LONG]', ['TITLE' => 'Titel'])
        );
    }

    public function testValuesAreNotReplacedAgain(): void
    {
        $this->assertSame('Text mit [TITLE]', Placeholders::render('[CONTENT]', ['CONTENT' => 'Text mit [TITLE]', 'TITLE' => 'x']));
    }

    public function testNamesCanBeLimited(): void
    {
        $this->assertSame('1-5 [TITLE]', Placeholders::render('[OF] [TITLE]', ['OF' => '1-5', 'TITLE' => 'x'], Placeholders::PAGINATION));
    }

    public function testFiftyTwoNames(): void
    {
        $this->assertCount(52, array_unique(Placeholders::NAMES));
    }

    public function testImageLoop(): void
    {
        $this->assertSame(
            '<img src="a.jpg" alt="Bild" width="100">[TITLE]',
            Placeholders::renderImage('<img src="[IMAGE]" alt="[DESCRIPTION]" width="[THUMBWIDTH]">[TITLE]', ['IMAGE' => 'a.jpg', 'DESCRIPTION' => 'Bild', 'THUMBWIDTH' => 100])
        );
    }

    public function testTagColors(): void
    {
        $this->assertSame('#ffffff', Tags::contrastColor('#314B68'));
        $this->assertSame('#000000', Tags::contrastColor('#fff'));
        $this->assertSame('', Tags::contrastColor('red'));
        $this->assertSame('', Tags::safeColor('red;background:url(x)'));
        $this->assertSame('rgb(1, 2, 3)', Tags::safeColor(' rgb(1, 2, 3) '));
        $this->assertSame(' style="background-color:#9BA9B9;color:#000000"', Tags::styleAttr(['color' => '#9BA9B9']));
        $this->assertSame(' style="background-color:#000;color:red"', Tags::styleAttr(['color' => '#000', 'text_color' => 'red']));
        $this->assertSame('', Tags::styleAttr(['color' => '"><script>']));
    }

    public function testFiltersIgnoreInvalidValues(): void
    {
        $this->assertSame(
            ['p' => 0, 'g' => null, 'tags' => [], 'm' => null, 'y' => null, 'method' => null],
            Frontend::filters(['p' => 'abc', 'g' => '-1', 'tags' => ['x'], 'm' => '13', 'y' => '1969', 'method' => '2'])
        );
        $this->assertSame(
            ['p' => 5, 'g' => 7, 'tags' => ['Saisonal', 'Region'], 'm' => 9, 'y' => 2026, 'method' => 1],
            Frontend::filters(['p' => '5', 'g' => '7', 'tags' => ' Saisonal,,Region ', 'm' => '9', 'y' => '2026', 'method' => '1'])
        );
    }

    public function testMonthFilterUsesTheTimeOfTheWebsite(): void
    {
        defined('TIMEZONE') || define('TIMEZONE', 3600);
        [$from, $to] = Frontend::monthRange(10, 2026);
        // 1 October 00:30 on the website is 30 September 23:30 UTC (TIMEZONE 3600): October, not September
        $justAfterMidnight = gmmktime(0, 30, 0, 10, 1, 2026) - TIMEZONE;
        $this->assertTrue($from <= $justAfterMidnight && $justAfterMidnight < $to);
        $this->assertSame(gmmktime(0, 0, 0, 11, 1, 2026) - TIMEZONE, $to);
        // December ends with the next year
        $this->assertSame(gmmktime(0, 0, 0, 1, 1, 2027) - TIMEZONE, Frontend::monthRange(12, 2026)[1]);
    }

    public function testUrl(): void
    {
        $this->assertSame('?p=0', Frontend::url('', ['p' => 0, 'g' => null, 'tags' => '']));
        $this->assertSame('/x.php?a=1&tags=A%20B', Frontend::url('/x.php?a=1', ['tags' => 'A B']));
        $this->assertSame('/x.php', Frontend::url('/x.php', ['g' => null]));
    }
}
