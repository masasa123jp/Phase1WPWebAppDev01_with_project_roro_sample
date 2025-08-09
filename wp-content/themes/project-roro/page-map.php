<?php
/* Template Name: マップページ */
get_header();
?>
<header class="app-header">
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="ロゴ" class="small-logo" />
  <h2 data-i18n-key="map_title">おでかけマップ</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/switch-language.png" alt="Language" />
  </button>
</header>
<main id="map-container">
  <div id="category-bar" class="category-bar"></div>
  <div id="map"></div>
  <button id="reset-view-btn" class="reset-btn" title="周辺表示" data-i18n-key="reset_view">周辺表示</button>
</main>
<?php get_footer(); ?>
