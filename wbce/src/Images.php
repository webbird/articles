<?php
/**
 * articles – Images
 * One upload check for every kind of image, originals plus derived sizes, and
 * rebuilding the sizes of a section. Folders below media/.articles:
 * items/<id>/ (preview image and gallery), groups/<id>/, sections/<id>/,
 * each original with the derived sizes in preview/, thumb/ and large/.
 * @module articles  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

declare(strict_types=1);

namespace Articles;

final class Images
{
    /** Longest edge of an original. */
    public const MAX_EDGE = 2560;
    public const QUALITY = 85;

    /** getimagesize() type => allowed extensions; the type has to match the extension. */
    public const TYPES = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG  => ['png'],
        IMAGETYPE_GIF  => ['gif'],
        IMAGETYPE_WEBP => ['webp'],
    ];

    /** Derived sizes per kind of image (Konzept, section 4 "Bilder"). */
    public const PREVIEW = ['preview'];
    public const GALLERY = ['thumb', 'large'];

    public static function typeMatches(int $type, string $extension): bool
    {
        return in_array(strtolower($extension), self::TYPES[$type] ?? [], true);
    }

    /**
     * Width and height to cut from the centre: the box itself if the image is
     * large enough, otherwise the largest area of the box's aspect ratio, so nothing is enlarged.
     *
     * @return array{int, int}
     */
    public static function cropBox(int $width, int $height, int $boxWidth, int $boxHeight): array
    {
        $scale = max($boxWidth / $width, $boxHeight / $height);
        return $scale <= 1 ? [$boxWidth, $boxHeight] : [max(1, (int) floor($boxWidth / $scale)), max(1, (int) floor($boxHeight / $scale))];
    }

    /** @return array<string, array{int, int, bool}> size => width, height, crop */
    public static function sizes(array $settings): array
    {
        $sizes = [];
        foreach (['preview', 'thumb', 'large'] as $size) {
            $sizes[$size] = [max(1, (int) $settings[$size . '_width']), max(1, (int) $settings[$size . '_height']), (int) $settings[$size . '_crop'] === 1];
        }
        return $sizes;
    }

    /** "Foto Ä 1.JPG" => "foto-ae-1.jpg"; unused in $folder, numbered if taken. */
    public static function fileName(string $folder, string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string) page_filename(pathinfo($name, PATHINFO_FILENAME)))), '-_');
        $base = substr($base !== '' ? $base : 'image', 0, 100);
        $file = "$base.$extension";
        for ($n = 1; is_file("$folder/$file"); $n++) {
            $file = "$base-$n.$extension";
        }
        return $file;
    }

    /**
     * Checks one entry of $_FILES and stores it re-encoded as original in
     * $folder (below media/.articles), then derives $derived. Returns the file
     * name; throws DomainException with a language key, nothing stays behind.
     */
    public static function upload(array $file, string $folder, int $maxBytes, array $derived, array $sizes): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($error === UPLOAD_ERR_OK && (int) $file['size'] > $maxBytes)) {
            throw new \DomainException('ERR_IMAGE_SIZE');
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new \DomainException('ERR_IMAGE_UPLOAD');
        }
        return self::store($file['tmp_name'], (string) $file['name'], $folder, $derived, $sizes);
    }

    /** Stores an existing image file as new original in $folder (used by upload and for copies). */
    public static function store(string $source, string $name, string $folder, array $derived, array $sizes): string
    {
        $info = @getimagesize($source);
        if ($info === false || !self::typeMatches((int) $info[2], pathinfo($name, PATHINFO_EXTENSION))) {
            throw new \DomainException('ERR_IMAGE_TYPE');
        }
        try {
            // a valid header is not enough: e.g. "GIF89a<?php …" passes getimagesize()
            $image = self::image($source);
        } catch (\Throwable) {
            throw new \DomainException('ERR_IMAGE_TYPE');
        }
        $dir = \ARTICLES_MEDIA_PATH . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \DomainException('ERR_IMAGE_WRITE');
        }
        $file = self::fileName($dir, $name);
        try {
            // Always re-encoded: drops anything hidden behind the image data (and EXIF)
            $image->autoOrient()->bestFit(self::MAX_EDGE, self::MAX_EDGE)->toFile("$dir/$file", $info['mime'], self::QUALITY);
            self::derive($folder, $file, $derived, $sizes);
        } catch (\Throwable $e) {
            self::remove($folder, $file, $derived);
            throw $e instanceof \DomainException ? $e : new \DomainException('ERR_IMAGE_WRITE');
        }
        return $file;
    }

    /** Creates the derived sizes of an original; never enlarges. */
    public static function derive(string $folder, string $file, array $derived, array $sizes): void
    {
        $original = \ARTICLES_MEDIA_PATH . "$folder/$file";
        foreach ($derived as $size) {
            [$width, $height, $crop] = $sizes[$size];
            $dir = \ARTICLES_MEDIA_PATH . "$folder/$size";
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new \DomainException('ERR_IMAGE_WRITE');
            }
            $image = self::image($original);
            if ($crop) {
                [$cropWidth, $cropHeight] = self::cropBox($image->getWidth(), $image->getHeight(), $width, $height);
                $image->thumbnail($cropWidth, $cropHeight);
            } else {
                $image->bestFit($width, $height);
            }
            $image->toFile("$dir/$file", null, self::QUALITY);
        }
    }

    /** Original and derived files; '' does nothing. */
    public static function remove(string $folder, string $file, array $derived): void
    {
        if ($file === '' || basename($file) !== $file) {
            return;
        }
        foreach (array_merge([''], array_map(static fn (string $size): string => "/$size", $derived)) as $sub) {
            $path = \ARTICLES_MEDIA_PATH . "$folder$sub/$file";
            is_file($path) && unlink($path);
        }
    }

    /**
     * Preview image, group image or default image: a new upload replaces the
     * old file, $remove deletes it. Returns the file name to store ('' = none).
     */
    public static function replace(string $folder, string $current, ?array $upload, bool $remove, int $maxBytes, array $sizes): string
    {
        if ($upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = self::upload($upload, $folder, $maxBytes, self::PREVIEW, $sizes);
            self::remove($folder, $current, self::PREVIEW);
            return $file;
        }
        if ($remove) {
            self::remove($folder, $current, self::PREVIEW);
            return '';
        }
        return $current;
    }

    /** Adds one upload to the gallery of an article (last position); returns the new row. */
    public static function addToGallery(\Database $db, int $articleId, array $upload, int $maxBytes, array $sizes): array
    {
        $file = self::upload($upload, "/items/$articleId", $maxBytes, self::GALLERY, $sizes);
        $position = (int) $db->fetchValue('SELECT MAX(`position`) FROM `{TP}mod_articles_images` WHERE `article_id` = ?', [$articleId]) + 1;
        try {
            $id = Articles::insert($db, 'images', ['article_id' => $articleId, 'file' => $file, 'caption' => '', 'position' => $position]);
        } catch (\RuntimeException $e) {
            self::remove("/items/$articleId", $file, self::GALLERY);
            throw $e;
        }
        return ['image_id' => $id, 'file' => $file, 'caption' => '', 'position' => $position];
    }

    /**
     * Captions, order (image ids first to last) and deletions of a gallery.
     * Ids of other articles are ignored.
     */
    public static function saveGallery(\Database $db, int $articleId, array $captions, array $order, array $delete): void
    {
        $files = array_column($db->fetchAll('SELECT `image_id`, `file` FROM `{TP}mod_articles_images` WHERE `article_id` = ?', [$articleId]), 'file', 'image_id');
        $delete = array_values(array_intersect(array_map('intval', $delete), array_keys($files)));
        $order = array_values(array_unique(array_intersect(array_map('intval', $order), array_keys($files))));
        $db->transaction(static function (\Database $db) use ($articleId, $captions, $order, $delete, $files): void {
            foreach ($files as $id => $file) {
                if (is_string($captions[$id] ?? null)) {
                    Articles::exec($db, 'UPDATE `{TP}mod_articles_images` SET `caption` = ? WHERE `image_id` = ?', [mb_substr(trim($captions[$id]), 0, 255), $id]);
                }
            }
            foreach ($order as $i => $id) {
                Articles::exec($db, 'UPDATE `{TP}mod_articles_images` SET `position` = ? WHERE `image_id` = ?', [$i + 1, $id]);
            }
            if ($delete !== []) {
                Articles::exec($db, 'DELETE FROM `{TP}mod_articles_images` WHERE `image_id` IN ' . Articles::in($delete));
            }
        });
        foreach ($delete as $id) {
            self::remove("/items/$articleId", (string) $files[$id], self::GALLERY);
        }
    }

    /** One step up or down; renumbers the gallery. */
    public static function stepGallery(\Database $db, int $articleId, int $imageId, bool $up): void
    {
        $ids = self::galleryIds($db, $articleId);
        $pos = array_search($imageId, $ids, true);
        $other = is_int($pos) ? $pos + ($up ? -1 : 1) : -1;
        if (isset($ids[$other])) {
            [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];
            self::saveGallery($db, $articleId, [], $ids, []);
        }
    }

    /** @return list<int> image ids in gallery order */
    public static function galleryIds(\Database $db, int $articleId): array
    {
        return array_map('intval', array_column(
            $db->fetchAll('SELECT `image_id` FROM `{TP}mod_articles_images` WHERE `article_id` = ? ORDER BY `position`, `image_id`', [$articleId]),
            'image_id'
        ));
    }

    /**
     * Every [folder, file, derived sizes] of a section: preview images and
     * galleries of its articles, group images, default image.
     *
     * @return list<array{string, string, list<string>}>
     */
    public static function ofSection(\Database $db, int $sectionId): array
    {
        $list = [];
        foreach ($db->fetchAll("SELECT `article_id`, `image` FROM `{TP}mod_articles_items` WHERE `section_id` = ? AND `image` <> ''", [$sectionId]) as $a) {
            $list[] = ['/items/' . (int) $a['article_id'], (string) $a['image'], self::PREVIEW];
        }
        foreach ($db->fetchAll(
            'SELECT m.`article_id`, m.`file` FROM `{TP}mod_articles_images` m JOIN `{TP}mod_articles_items` i ON i.`article_id` = m.`article_id` WHERE i.`section_id` = ?',
            [$sectionId]
        ) as $m) {
            $list[] = ['/items/' . (int) $m['article_id'], (string) $m['file'], self::GALLERY];
        }
        foreach ($db->fetchAll("SELECT `group_id`, `image` FROM `{TP}mod_articles_groups` WHERE `section_id` = ? AND `image` <> ''", [$sectionId]) as $g) {
            $list[] = ['/groups/' . (int) $g['group_id'], (string) $g['image'], self::PREVIEW];
        }
        $default = (string) $db->fetchValue('SELECT `default_image` FROM `{TP}mod_articles_settings` WHERE `section_id` = ?', [$sectionId]);
        if ($default !== '') {
            $list[] = ["/sections/$sectionId", $default, self::PREVIEW];
        }
        return $list;
    }

    /**
     * Derives all sizes of a section anew from the originals.
     * shortcut: one request for the whole section; several thousand images may hit PHP's time limit, then work in batches.
     *
     * @return array{int, list<string>} number of images, files that failed
     */
    public static function rebuild(\Database $db, int $sectionId): array
    {
        set_time_limit(0);
        $sizes = self::sizes(Settings::get($db, $sectionId) ?? Settings::DEFAULTS);
        $done = 0;
        $failed = [];
        foreach (self::ofSection($db, $sectionId) as [$folder, $file, $derived]) {
            try {
                if (!is_file(\ARTICLES_MEDIA_PATH . "$folder/$file")) {
                    throw new \DomainException('ERR_IMAGE_WRITE');
                }
                self::derive($folder, $file, $derived, $sizes);
                $done++;
            } catch (\Throwable) {
                $failed[] = "$folder/$file";
            }
        }
        return [$done, $failed];
    }

    /** Rearranges $_FILES['name'] of a multiple upload into one array per file. */
    public static function filesOf(string $name): array
    {
        $f = $_FILES[$name] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return [];
        }
        $files = [];
        foreach (array_keys($f['name']) as $i) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        }
        return $files;
    }

    /** One entry of $_FILES, null if missing or a multiple upload. */
    public static function fileOf(string $name): ?array
    {
        $f = $_FILES[$name] ?? null;
        return is_array($f) && !is_array($f['name'] ?? null) ? $f : null;
    }

    private static function image(string $file): \claviska\SimpleImage
    {
        if (!class_exists(\claviska\SimpleImage::class, false)) {
            require_once WB_PATH . '/include/claviska/SimpleImage.php';
        }
        return (new \claviska\SimpleImage())->fromFile($file);
    }
}
