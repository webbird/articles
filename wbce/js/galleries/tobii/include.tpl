<?php
$tobiiText = static fn (string $key): string => (string) \Lang::get('MOD_ARTICLES', $key, $key);
$tobiiOptions = [
    'selector'           => '.articles-tobii-item',
    'navLabel'           => [$tobiiText('TOBII_PREVIOUS'), $tobiiText('TOBII_NEXT')],
    'closeLabel'         => $tobiiText('TOBII_CLOSE'),
    'dialogTitle'        => $tobiiText('TOBII_DIALOG'),
    'announcementLabel'  => [$tobiiText('TOBII_IMAGE'), $tobiiText('TOBII_OF')],
    'loadingIndicatorLabel' => $tobiiText('TOBII_LOADING'),
    'captionToggleLabel' => [$tobiiText('TOBII_CAPTION_HIDE'), $tobiiText('TOBII_CAPTION_SHOW')],
];
?>
<link rel="stylesheet" href="<?php echo WB_URL ?>/modules/articles/js/galleries/tobii/tobii.min.css" />
<link rel="stylesheet" href="<?php echo WB_URL ?>/modules/articles/js/galleries/tobii/gallery.css" />
<script src="<?php echo WB_URL ?>/modules/articles/js/galleries/tobii/tobii.min.js"></script>
<script>
    // This include comes before the gallery markup, so wait for the DOM
    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelector('.articles-tobii-item')) {
            new Tobii(<?php echo json_encode($tobiiOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);
        }
    });
</script>
