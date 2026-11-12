<?php

declare(strict_types=1);

use Articles\Images;
use PHPUnit\Framework\TestCase;

final class ImagesTest extends TestCase
{
    public function testTypeHasToMatchTheExtension(): void
    {
        $this->assertTrue(Images::typeMatches(IMAGETYPE_JPEG, 'JPEG'));
        $this->assertTrue(Images::typeMatches(IMAGETYPE_WEBP, 'webp'));
        $this->assertFalse(Images::typeMatches(IMAGETYPE_PNG, 'jpg'));
        $this->assertFalse(Images::typeMatches(IMAGETYPE_JPEG, 'php'));
        $this->assertFalse(Images::typeMatches(IMAGETYPE_BMP, 'bmp'));
    }

    public function testCropNeverEnlarges(): void
    {
        // large enough: exactly the box
        $this->assertSame([125, 125], Images::cropBox(2400, 1600, 125, 125));
        // too small: largest area of the box's aspect ratio inside the image
        $this->assertSame([80, 80], Images::cropBox(100, 80, 125, 125));
        $this->assertSame([300, 150], Images::cropBox(300, 400, 900, 450));
    }

    public function testSizesComeFromTheSettings(): void
    {
        $settings = ['preview_width' => 125, 'preview_height' => 100, 'preview_crop' => 1,
            'thumb_width' => 0, 'thumb_height' => 80, 'thumb_crop' => 0,
            'large_width' => 900, 'large_height' => 900, 'large_crop' => 0];
        $this->assertSame(
            ['preview' => [125, 100, true], 'thumb' => [1, 80, false], 'large' => [900, 900, false]],
            Images::sizes($settings)
        );
    }
}
