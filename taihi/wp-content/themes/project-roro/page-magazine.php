<?php
/* Template Name: 雑誌ページ */
get_header();
?>
<header class="app-header">
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="ロゴ" class="small-logo" />
  <h2 data-i18n-key="magazine_title">月間雑誌</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/switch-language.png" alt="Language" />
  </button>
</header>
<main class="magazine-grid">
  <div class="magazine-card">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/magazine_cover1.png" alt="2025年6月号" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_june">2025年6月号</h3>
      <p data-i18n-key="mag_desc_june">雨の日でも犬と楽しく過ごせる特集</p>
    </div>
  </div>
  <div class="magazine-card">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/magazine_cover2.png" alt="2025年7月号" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_july">2025年7月号</h3>
      <p data-i18n-key="mag_desc_july">紫外線対策とワンちゃんとのおでかけスポットをご紹介♪</p>
    </div>
  </div>
</main>
<?php get_footer(); ?>
