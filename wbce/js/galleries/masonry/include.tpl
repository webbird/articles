<script src="<?php echo WB_URL ?>/modules/articles/js/galleries/masonry/masonry.min.js"></script>
<script src="<?php echo WB_URL ?>/modules/articles/js/galleries/masonry/imagesLoaded.min.js"></script>
<link rel="stylesheet" href="<?php echo WB_URL ?>/modules/articles/js/galleries/masonry/masonry.css" />


<script>
    // This include comes before the gallery markup, so wait for the DOM (news_img ran it too early)
    jQuery(function ($) {
      var $grid = $('.masonry-grid').imagesLoaded(function () {
        $grid.masonry({
          itemSelector: '.masonry-grid-item',
          percentPosition: true,
          columnWidth: '.masonry-grid-sizer'
        });
      });
    });
</script>