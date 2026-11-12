<?php
/**
 * articles – Tags
 * Colors and markup of tags in the frontend. Managing tags follows with the backend (Phase 4).
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Tags
{
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
