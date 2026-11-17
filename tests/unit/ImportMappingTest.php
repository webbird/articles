<?php

declare(strict_types=1);

use Articles\Import;
use PHPUnit\Framework\TestCase;

final class ImportMappingTest extends TestCase
{
    /** Settings row as news_img 5.0.32 stores it (values of the seed script). */
    private const NEWS_IMG = [
        'header'                    => '',
        'post_loop'                 => '<div class="mod_nwi_group">[TITLE]</div>',
        'footer'                    => '<table class="mod_nwi_table">[OF]</table>',
        'block2'                    => '<p>[TITLE]</p>',
        'post_header'               => '<h2>[TITLE]</h2>',
        'post_content'              => '<div class="mod_nwi_content_short">[CONTENT_SHORT]</div>',
        'post_footer'               => '<div class="mod_nwi_tags">[TAGS]</div>',
        'image_loop'                => '<img src="[IMAGE]" />',
        'view_order'                => 3,
        'posts_per_page'            => 5,
        'resize_preview'            => '300x200',
        'crop_preview'              => 'Y',
        'gallery'                   => 'masonry',
        'imgthumbsize'              => '150x150',
        'imgmaxwidth'               => '800',
        'imgmaxheight'              => '600',
        'imgmaxsize'                => '2097152',
        'use_second_block'          => 'Y',
        'view'                      => 'grid',
        'mode'                      => 'advanced',
        'show_settings_only_admins' => 'Y',
    ];

    public function testMapsAllFieldsOfNewsImg5(): void
    {
        [$settings, $notes] = Import::mapNewsImgSettings(self::NEWS_IMG);

        self::assertSame([], $notes);
        self::assertSame('<div class="mod_articles_group">[TITLE]</div>', $settings['tpl_list_item']);
        self::assertSame('<table class="mod_articles_table">[OF]</table>', $settings['tpl_list_footer']);
        self::assertSame('<div class="mod_articles_content_short">[CONTENT_SHORT]</div>', $settings['tpl_detail_content']);
        self::assertSame('<div class="mod_articles_tags">[TAGS]</div>', $settings['tpl_detail_footer']);
        self::assertSame('<p>[TITLE]</p>', $settings['tpl_block2']);
        self::assertSame([300, 200, 1], [$settings['preview_width'], $settings['preview_height'], $settings['preview_crop']]);
        self::assertSame([150, 150, 1], [$settings['thumb_width'], $settings['thumb_height'], $settings['thumb_crop']]);
        self::assertSame([800, 600, 1], [$settings['large_width'], $settings['large_height'], $settings['large_crop']]);
        self::assertSame([3, 5, 2097152], [$settings['sort_order'], $settings['per_page'], $settings['max_upload']]);
        self::assertSame(['grid', 'masonry'], [$settings['view'], $settings['gallery']]);
        self::assertSame([1, 1, 1], [$settings['use_block2'], $settings['expert_mode'], $settings['settings_admins_only']]);
        self::assertSame(['', ''], [$settings['articles_dir'], $settings['default_image']]);
    }

    public function testCoversEveryColumnExceptSectionId(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/wbce/install_struct.sql');
        preg_match('/mod_articles_settings` \((.*?)PRIMARY KEY/s', $sql, $table);
        preg_match_all('/^\s+`(\w+)`/m', $table[1], $columns);

        $mapped = array_keys(Import::mapNewsImgSettings([])[0]);
        sort($mapped);
        $expected = array_values(array_diff($columns[1], ['section_id']));
        sort($expected);
        self::assertSame($expected, $mapped);
    }

    public function testReplacesUnknownViewAndGalleryWithNote(): void
    {
        [$settings, $notes] = Import::mapNewsImgSettings(['view' => 'faq', 'gallery' => 'slick'] + self::NEWS_IMG);

        self::assertSame(['default', 'fotorama'], [$settings['view'], $settings['gallery']]);
        self::assertSame([['IMPORT_VIEW_REPLACED', 'faq'], ['IMPORT_GALLERY_REPLACED', 'slick']], $notes);
    }

    public function testLightcaseBecomesTobiiWithNote(): void
    {
        [$settings, $notes] = Import::mapNewsImgSettings(['gallery' => 'lightcase'] + self::NEWS_IMG);

        self::assertSame('tobii', $settings['gallery']);
        self::assertSame([['IMPORT_GALLERY_TOBII', 'lightcase']], $notes);
    }

    public function testDefaultsForMissingAndEmptyValues(): void
    {
        [$settings] = Import::mapNewsImgSettings([
            'resize_preview' => null, 'imgthumbsize' => '', 'imgmaxsize' => '', 'crop_preview' => 'N', 'mode' => 'default',
        ]);

        self::assertSame([0, 0, 0], [$settings['preview_width'], $settings['thumb_height'], $settings['large_crop']]);
        self::assertSame(ini_parse_quantity((string) ini_get('upload_max_filesize')), $settings['max_upload']);
        self::assertSame(['default', 'fotorama', 0], [$settings['view'], $settings['gallery'], $settings['expert_mode']]);
    }

    public function testCleansFieldsOfNewsImg6(): void
    {
        [$settings] = Import::mapNewsImgSettings(['posts_dir' => '../neu/s', 'default_preview_image' => '../../config.php'] + self::NEWS_IMG);

        self::assertSame(['neus', 'config.php'], [$settings['articles_dir'], $settings['default_image']]);
    }
}
