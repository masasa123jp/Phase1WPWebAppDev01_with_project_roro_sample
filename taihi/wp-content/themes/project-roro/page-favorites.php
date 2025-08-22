<?php
/* Template Name: お気に入りページ */
get_header();
?>
<header class="app-header">
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="ロゴ" class="small-logo" />
  <h2 data-i18n-key="favorites_title">お気に入り</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/switch-language.png" alt="Language" />
  </button>
</header>
<main id="favorites-container">
  <ul id="favorites-list"></ul>
  <p id="no-favorites" style="display:none;" data-i18n-key="no_favorites">お気に入りがまだありません。</p>
</main>
<?php get_footer(); ?>
