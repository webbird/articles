<?php
/**
 * articles – Placeholders
 * Replaces [NAME] in the frontend templates. Pure: no database, no globals.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Placeholders
{
    /** The 52 names of news_img; only these are replaced, any other [TEXT] stays as it is. */
    public const NAMES = [
        'BACK', 'CONTENT', 'CONTENT_BLOCK2', 'CONTENT_LONG', 'CONTENT_SHORT', 'CREATED_DATE', 'CREATED_TIME',
        'DISPLAY_GROUP', 'DISPLAY_IMAGE', 'DISPLAY_NAME', 'DISPLAY_PREVIOUS_NEXT_LINKS', 'EMAIL',
        'GROUP_ID', 'GROUP_IMAGE', 'GROUP_IMAGE_URL', 'GROUP_TITLE', 'IMAGE', 'IMAGE_URL', 'IMAGES',
        'LINK', 'HREF', 'AOPEN', 'ACLOSE', 'MODI_DATE', 'MODI_TIME', 'NEXT_LINK', 'NEXT_PAGE_LINK',
        'OF', 'OUT_OF', 'PAGE_TITLE', 'POST_ID', 'POST_OR_GROUP_IMAGE', 'PREVIOUS_LINK', 'PREVIOUS_PAGE_LINK',
        'PUBLISHED_DATE', 'PUBLISHED_TIME', 'SHORT', 'SHOW_READ_MORE', 'TAGS', 'TAGLIST',
        'TEXT_AT', 'TEXT_BACK', 'TEXT_LAST_CHANGED', 'TEXT_NEXT_POST', 'TEXT_O_CLOCK', 'TEXT_ON',
        'TEXT_POSTED_BY', 'TEXT_PREV_POST', 'TEXT_READ_MORE', 'TITLE', 'USER_ID', 'USERNAME',
    ];

    /** The placeholders of the pagination in list header and footer; news_img replaces only these there. */
    public const PAGINATION = [
        'NEXT_PAGE_LINK', 'NEXT_LINK', 'PREVIOUS_PAGE_LINK', 'PREVIOUS_LINK', 'OUT_OF', 'OF', 'DISPLAY_PREVIOUS_NEXT_LINKS',
    ];

    /**
     * Known names without a value become empty. One pass, so a value that
     * contains "[TITLE]" (e.g. in the article text) is not replaced again.
     *
     * @param array<string, string|int> $values
     * @param list<string>               $names
     */
    public static function render(string $template, array $values, array $names = self::NAMES): string
    {
        return (string) preg_replace_callback(
            '/\[(' . implode('|', $names) . ')\]/',
            static fn (array $m): string => (string) ($values[$m[1]] ?? ''),
            $template
        );
    }

    /** One gallery image: [IMAGE] [DESCRIPTION] [THUMB] [THUMBWIDTH] [THUMBHEIGHT] [WB_URL]. */
    public static function renderImage(string $template, array $values): string
    {
        return self::render($template, $values, ['IMAGE', 'DESCRIPTION', 'THUMB', 'THUMBWIDTH', 'THUMBHEIGHT', 'WB_URL']);
    }
}
