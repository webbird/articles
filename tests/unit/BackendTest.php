<?php

declare(strict_types=1);

use Articles\Articles;
use Articles\Backend;
use PHPUnit\Framework\TestCase;

final class BackendTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // WBCE: offset of the backend user's time zone in seconds
        defined('TIMEZONE') || define('TIMEZONE', 3600);
    }

    public function testDatesRoundTripInTheUsersTime(): void
    {
        $this->assertSame(0, Backend::toTimestamp(''));
        $this->assertNull(Backend::toTimestamp('2026-13-01T10:00'));
        $this->assertNull(Backend::toTimestamp('gestern'));
        $ts = Backend::toTimestamp('2026-10-01T12:00');
        $this->assertSame(gmmktime(12, 0, 0, 10, 1, 2026) - TIMEZONE, $ts);
        $this->assertSame('2026-10-01T12:00', Backend::fromTimestamp($ts));
        $this->assertSame('', Backend::fromTimestamp(0));
    }

    public function testStatusFollowsTheVisibilityRule(): void
    {
        $now = 1_800_000_000;
        $a = ['active' => 1, 'published_from' => 0, 'published_until' => 0];
        $this->assertSame('visible', Articles::status($a, true, $now));
        $this->assertSame('hidden', Articles::status($a, false, $now));
        $this->assertSame('inactive', Articles::status(['active' => 0] + $a, true, $now));
        $this->assertSame('planned', Articles::status(['published_from' => $now + 1] + $a, true, $now));
        $this->assertSame('expired', Articles::status(['published_until' => $now - 1] + $a, true, $now));
        $this->assertSame('visible', Articles::status(['published_from' => $now, 'published_until' => $now] + $a, true, $now));
    }

    public function testArrowsSwapWithTheNeighbour(): void
    {
        $this->assertSame([2, 1, 3], Articles::swap([1, 2, 3], 2, true));
        $this->assertSame([1, 3, 2], Articles::swap([1, 2, 3], 2, false));
        $this->assertNull(Articles::swap([1, 2, 3], 1, true));
        $this->assertNull(Articles::swap([1, 2, 3], 3, false));
        $this->assertNull(Articles::swap([1, 2, 3], 9, true));
    }

    public function testVisibilityRuleCanLetPlannedArticlesThrough(): void
    {
        $this->assertStringContainsString('`published_from` <= 100', Articles::visibleSql('i', 100));
        $planned = Articles::visibleSql('i', 100, true);
        $this->assertStringNotContainsString('published_from', $planned);
        $this->assertStringContainsString('`published_until` >= 100', $planned);
        $this->assertStringContainsString('gv.`active` = 1', $planned);
    }

    public function testInListIsIntegersOnly(): void
    {
        $this->assertSame('(1,2)', Articles::in(['1', 2, '2', '1; DROP TABLE x']));
        $this->assertSame('(0)', Articles::in([]));
    }
}
