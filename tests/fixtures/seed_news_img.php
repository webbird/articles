<?php
/**
 * Fills one news_img 5.0.x section with fixed test data: the source for the
 * articles import and for the reference outputs (docs/Konzept.md, Phase 0).
 *
 * Usage: php seed_news_img.php <wbce-dir> <section_id>
 *
 * Replaces all news_img data of that section (posts, images, groups, its tags
 * and the global test tag). Other sections stay untouched. Writes the
 * database, files below media/.news_img and the access files of the posts,
 * the way news_img 5.0.32 lays them out. Images are drawn with GD.
 */

declare(strict_types=1);

if ($argc !== 3 || !is_file($argv[1] . '/config.php') || !ctype_digit($argv[2])) {
    fwrite(STDERR, "Usage: php seed_news_img.php <wbce-dir> <section_id>\n");
    exit(1);
}
$wbPath    = rtrim(str_replace('\\', '/', $argv[1]), '/');
$sectionId = (int) $argv[2];

// ── Database from config.php, without booting WBCE ─────────────────────────
$config = (string) file_get_contents($wbPath . '/config.php');
$const  = static function (string $name) use ($config): string {
    if (!preg_match("/define\\(\\s*'" . $name . "'\\s*,\\s*'([^']*)'/", $config, $m)) {
        fwrite(STDERR, "$name not found in config.php\n");
        exit(1);
    }
    return $m[1];
};
[$host, $port] = array_pad(explode(':', $const('DB_HOST'), 2), 2, '3306');
$tp  = $const('TABLE_PREFIX');
$db  = new PDO(
    "mysql:host=$host;port=$port;dbname=" . $const('DB_NAME') . ';charset=utf8mb4',
    $const('DB_USERNAME'),
    $const('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$run = static function (string $sql, array $params = []) use ($db, $tp): PDOStatement {
    $stmt = $db->prepare(str_replace('{TP}', $tp, $sql));
    $stmt->execute($params);
    return $stmt;
};
$setting = static fn (string $name, string $default): string
    => (string) ($run('SELECT `value` FROM `{TP}settings` WHERE `name` = ?', [$name])->fetchColumn() ?: $default);

$pagesDir  = $setting('pages_directory', '/pages');
$extension = $setting('page_extension', '.php');
$spacer    = $setting('page_spacer', '-');
$mediaDir  = $wbPath . $setting('media_directory', '/media') . '/.news_img/';

$section = $run('SELECT `page_id`, `module` FROM `{TP}sections` WHERE `section_id` = ?', [$sectionId])->fetch();
if (!$section || $section['module'] !== 'news_img') {
    fwrite(STDERR, "Section $sectionId is not a news_img section.\n");
    exit(1);
}
$pageId = (int) $section['page_id'];
if (!$run('SELECT 1 FROM `{TP}mod_news_img_settings` WHERE `section_id` = ?', [$sectionId])->fetchColumn()) {
    fwrite(STDERR, "No news_img settings for section $sectionId. Was the section added in the backend?\n");
    exit(1);
}
$userId = (int) $run('SELECT MIN(`user_id`) FROM `{TP}users`')->fetchColumn();

const GLOBAL_TAG = 'Wichtig';
$at = static fn (string $date): int => (int) (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp();

// ── Remove what a previous run left ────────────────────────────────────────
foreach ($run('SELECT `post_id`, `link`, `image` FROM `{TP}mod_news_img_posts` WHERE `section_id` = ?', [$sectionId]) as $old) {
    @unlink($wbPath . $pagesDir . $old['link'] . $extension);
    if ($old['image'] !== '') {
        @unlink($mediaDir . basename($old['image']));
    }
    $dir = $mediaDir . (int) $old['post_id'];
    foreach ([$dir . '/thumb', $dir] as $d) {
        foreach (glob($d . '/*') ?: [] as $f) {
            is_file($f) && unlink($f);
        }
        is_dir($d) && rmdir($d);
    }
    $run('DELETE FROM `{TP}mod_news_img_img` WHERE `post_id` = ?', [$old['post_id']]);
    $run('DELETE FROM `{TP}mod_news_img_tags_posts` WHERE `post_id` = ?', [$old['post_id']]);
}
$run('DELETE FROM `{TP}mod_news_img_posts` WHERE `section_id` = ?', [$sectionId]);
foreach ($run('SELECT `group_id` FROM `{TP}mod_news_img_groups` WHERE `section_id` = ?', [$sectionId]) as $old) {
    foreach (glob($mediaDir . 'image' . (int) $old['group_id'] . '.*') ?: [] as $f) {
        unlink($f);
    }
}
$run('DELETE FROM `{TP}mod_news_img_groups` WHERE `section_id` = ?', [$sectionId]);
$oldTags = $run(
    'SELECT `tag_id` FROM `{TP}mod_news_img_tags_sections` WHERE `section_id` = ?
     UNION SELECT s.`tag_id` FROM `{TP}mod_news_img_tags_sections` s JOIN `{TP}mod_news_img_tags` t ON t.`tag_id` = s.`tag_id`
      WHERE s.`section_id` = 0 AND t.`tag` = ?',
    [$sectionId, GLOBAL_TAG]
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($oldTags as $tagId) {
    $run('DELETE FROM `{TP}mod_news_img_tags_posts` WHERE `tag_id` = ?', [$tagId]);
    $run('DELETE FROM `{TP}mod_news_img_tags_sections` WHERE `tag_id` = ?', [$tagId]);
    $run('DELETE FROM `{TP}mod_news_img_tags` WHERE `tag_id` = ?', [$tagId]);
}

// ── Images ─────────────────────────────────────────────────────────────────
is_dir($mediaDir) || mkdir($mediaDir, 0755, true);
$image = static function (string $file, int $w, int $h, string $label, array $rgb): string {
    is_dir(dirname($file)) || mkdir(dirname($file), 0755, true);
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));
    $white = imagecolorallocate($img, 255, 255, 255);
    imagerectangle($img, 4, 4, $w - 5, $h - 5, $white);
    imagestring($img, 5, 12, 12, $label, $white);
    imagestring($img, 3, 12, 34, "{$w}x{$h}", $white);
    imagejpeg($img, $file, 80);
    return basename($file);
};
$navy  = [49, 75, 104];
$slate = [111, 131, 154];
$sand  = [176, 141, 87];

// ── Section settings (templates stay as add.php created them) ──────────────
$run(
    "UPDATE `{TP}mod_news_img_settings` SET
        `view_order` = 0, `posts_per_page` = 5, `resize_preview` = '300x200', `crop_preview` = 'Y',
        `imgthumbsize` = '150x150', `imgmaxwidth` = '800', `imgmaxheight` = '600',
        `gallery` = 'fotorama', `view` = 'default', `mode` = 'advanced',
        `use_second_block` = 'Y', `block2` = '<p class=\"test-block2\">Block 2 der Section: [TITLE]</p>'
      WHERE `section_id` = ?",
    [$sectionId]
);

// ── Groups ─────────────────────────────────────────────────────────────────
$groups = [];
foreach ([['Aktuelles', 1, true], ['Veranstaltungen', 1, false], ['Archiv', 0, true]] as $pos => [$title, $active, $withImage]) {
    $run(
        'INSERT INTO `{TP}mod_news_img_groups` (`section_id`, `active`, `position`, `title`) VALUES (?, ?, ?, ?)',
        [$sectionId, $active, $pos + 1, $title]
    );
    $groups[$title] = (int) $db->lastInsertId();
    if ($withImage) {
        $image($mediaDir . 'image' . $groups[$title] . '.jpg', 300, 200, "Gruppe $title", $slate);
    }
}

// ── Tags: two of the section, one global ───────────────────────────────────
$tags = [];
foreach ([['Saisonal', '#314B68', $sectionId], ['Region', '#9BA9B9', $sectionId], [GLOBAL_TAG, '#C0392B', 0]] as [$title, $color, $scope]) {
    $run('INSERT INTO `{TP}mod_news_img_tags` (`tag`, `tag_color`) VALUES (?, ?)', [$title, $color]);
    $tags[$title] = (int) $db->lastInsertId();
    $run('INSERT INTO `{TP}mod_news_img_tags_sections` (`section_id`, `tag_id`) VALUES (?, ?)', [$scope, $tags[$title]]);
}

// ── Posts ──────────────────────────────────────────────────────────────────
// Each case covers one rule of the rendering (preview, gallery, read more,
// visibility, time window, special characters). Dates are fixed so the
// reference output stays stable.
$long = '<p>Langtext mit <strong>Auszeichnung</strong> und einem '
      . '<a href="{SYSVAR:MEDIA_REL}/.news_img/">Link in den Medienordner</a>.</p>';
$posts = [
    ['Frühlingskarte ist da', 'fruehlingskarte', 'Aktuelles', 1, ['Saisonal', GLOBAL_TAG], 'landscape', ['landscape', 'portrait', 'landscape'], '2026-10-01 10:00', 0, $long, '<p>Eigener Block 2 des Beitrags.</p>'],
    ['Spargelzeit & Weinabend: „Genuss“ mit "Zitat"', 'spargelzeit-weinabend', 'Veranstaltungen', 1, ['Region'], 'portrait', [], '2026-10-02 18:30', 0, '', ''],
    ['Ohne Vorschaubild', 'ohne-vorschaubild', 'Aktuelles', 1, ['Saisonal'], null, [], '2026-10-03 09:00', 0, $long, ''],
    ['Ohne Gruppe', 'ohne-gruppe', null, 1, [], 'landscape', ['landscape'], '2026-10-04 12:00', 0, '', ''],
    ['Archivbeitrag in inaktiver Gruppe', 'archivbeitrag', 'Archiv', 1, [GLOBAL_TAG], 'landscape', [], '2026-10-05 08:00', 0, $long, ''],
    ['Inaktiver Beitrag', 'inaktiver-beitrag', 'Aktuelles', 0, [], 'landscape', [], '2026-10-06 08:00', 0, $long, ''],
    ['Geplanter Beitrag', 'geplanter-beitrag', 'Aktuelles', 1, [], null, [], '2030-01-01 00:00', 0, $long, ''],
    ['Abgelaufener Beitrag', 'abgelaufener-beitrag', 'Veranstaltungen', 1, [], null, [], '2019-12-01 00:00', $at('2020-01-01 00:00'), $long, ''],
    ['Meldung eins', 'meldung-eins', 'Aktuelles', 1, [], 'landscape', [], '2026-09-01 10:00', 0, '', ''],
    ['Meldung zwei', 'meldung-zwei', 'Veranstaltungen', 1, ['Region'], null, [], '2026-09-02 10:00', 0, $long, ''],
    ['Meldung drei', 'meldung-drei', 'Aktuelles', 1, [], 'portrait', [], '2026-09-03 10:00', 0, '', ''],
    ['Meldung vier', 'meldung-vier', null, 1, ['Saisonal'], null, [], '2026-09-04 10:00', 0, $long, ''],
];
$now     = time();
$created = [];
foreach ($posts as $pos => [$title, $slug, $group, $active, $postTags, $preview, $gallery, $when, $until, $content, $block2]) {
    $publishedWhen = $at($when);
    $run(
        'INSERT INTO `{TP}mod_news_img_posts`
            (`section_id`, `group_id`, `active`, `position`, `title`, `link`, `image`, `content_short`, `content_long`,
             `content_block2`, `published_when`, `published_until`, `posted_when`, `posted_by`)
         VALUES (?, ?, ?, ?, ?, \'\', \'\', ?, ?, ?, ?, ?, ?, ?)',
        [
            $sectionId, $group === null ? 0 : $groups[$group], $active, count($posts) - $pos, $title,
            '<p>Kurztext zu ' . htmlspecialchars($title) . '.</p>', $content, $block2,
            $publishedWhen, $until, $publishedWhen, $userId,
        ]
    );
    $postId = (int) $db->lastInsertId();
    $link   = '/posts/' . $slug . $spacer . $postId;

    $previewFile = '';
    if ($preview !== null) {
        [$w, $h] = $preview === 'portrait' ? [200, 300] : [300, 200];
        $previewFile = $image($mediaDir . "test-$slug.jpg", $w, $h, "Vorschau $postId", $navy);
    }
    $run('UPDATE `{TP}mod_news_img_posts` SET `link` = ?, `image` = ? WHERE `post_id` = ?', [$link, $previewFile, $postId]);

    foreach ($gallery as $n => $shape) {
        [$w, $h] = $shape === 'portrait' ? [450, 600] : [800, 600];
        $file = sprintf('bild-%02d.jpg', $n + 1);
        $image($mediaDir . "$postId/$file", $w, $h, "Galerie $postId/" . ($n + 1), $sand);
        $image($mediaDir . "$postId/thumb/$file", 150, 150, 'Thumb ' . ($n + 1), $sand);
        $run(
            'INSERT INTO `{TP}mod_news_img_img` (`picname`, `picdesc`, `post_id`, `position`) VALUES (?, ?, ?, ?)',
            [$file, 'Bildtext ' . ($n + 1) . ' zu „' . $title . '“', $postId, $n + 1]
        );
    }
    foreach ($postTags as $tag) {
        $run('INSERT INTO `{TP}mod_news_img_tags_posts` (`post_id`, `tag_id`) VALUES (?, ?)', [$postId, $tags[$tag]]);
    }

    // Access file in the format of news_img 5.0.32 (mod_nwi_create_file), for
    // the posts news_img itself would create one for. The file time is
    // CREATED_DATE in news_img.
    if ($active === 1 && $publishedWhen <= $now && ($until === 0 || $until >= $now)) {
        $file  = $wbPath . $pagesDir . $link . $extension;
        $depth = count(explode('/', $pagesDir)) - 1;
        is_dir(dirname($file)) || mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php\n\$page_id = $pageId;\n\$section_id = $sectionId;\n\$post_id = $postId;\n\n"
            . "define(\"POST_SECTION\", \$section_id);\ndefine(\"POST_ID\", \$post_id);\n"
            . 'require("' . str_repeat('../', $depth + 1) . "config.php\");\nrequire(WB_PATH.\"/index.php\");\n?>");
        touch($file, $publishedWhen);
    }
    $created[] = ['post_id' => $postId, 'title' => $title, 'link' => $link];
}

// ── Droplet calls in the first WYSIWYG section of the page, if there is one ─
$wysiwyg = $run(
    "SELECT `section_id` FROM `{TP}sections` WHERE `page_id` = ? AND `module` = 'wysiwyg' ORDER BY `position` LIMIT 1",
    [$pageId]
)->fetchColumn();
if ($wysiwyg !== false) {
    $droplets = '<h2>Droplet fetchNewsItems</h2>'
        . "<div class=\"test-droplet-list\">[[fetchNewsItems?group_id_type=section_id&group_id=$sectionId&max_news_items=4]]</div>"
        . "<h2>Droplet fetchNewsItems, nur Titel</h2>"
        . "<div class=\"test-droplet-aslist\">[[fetchNewsItems?group_id_type=section_id&group_id=$sectionId&aslist=1]]</div>"
        . '<h2>Droplet fetchNewsItems, Tag Saisonal</h2>'
        . '<div class="test-droplet-tags">[[fetchNewsItems?tags=Saisonal]]</div>';
    $run('UPDATE `{TP}mod_wysiwyg` SET `content` = ?, `text` = ? WHERE `section_id` = ?', [$droplets, strip_tags($droplets), $wysiwyg]);
}

// news_img hands block 2 over as MODULES_BLOCK2; only templates that ask for it
// show it. wbcezon ships with WBCE and does, so the reference contains block 2.
$run("UPDATE `{TP}pages` SET `template` = 'wbcezon' WHERE `page_id` = ?", [$pageId]);

echo json_encode(
    ['page_id' => $pageId, 'section_id' => $sectionId, 'wysiwyg_section' => $wysiwyg ?: null, 'groups' => $groups, 'tags' => $tags, 'posts' => $created],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), "\n";
