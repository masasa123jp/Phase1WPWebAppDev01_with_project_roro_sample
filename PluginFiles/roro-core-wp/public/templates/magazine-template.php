<?php
/**
 * 雑誌ページテンプレート
 * 表紙カードをクリックするとページめくりビューが開きます。
 */
?>
<header class="app-header">
  <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png') ?>" alt="logo" class="small-logo" />
  <h2 data-i18n-key="magazine_title">月間雑誌</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/switch-language.png') ?>" alt="Language" />
  </button>
</header>

<main class="magazine-grid">
  <div class="magazine-card" data-issue="2025-06">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/magazine_cover1.png') ?>" alt="2025年6月号" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_june">2025年6月号</h3>
      <p data-i18n-key="mag_desc_june">雨の日でも犬と楽しく過ごせる特集</p>
    </div>
  </div>
  <div class="magazine-card" data-issue="2025-07">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/magazine_cover2.png') ?>" alt="2025年7月号" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_july">2025年7月号</h3>
      <p data-i18n-key="mag_desc_july">紫外線対策とワンちゃんとのおでかけスポットをご紹介♪</p>
    </div>
  </div>
</main>

<div id="magazine-viewer" class="magazine-viewer" style="display:none;">
  <div class="book"></div>
</div>

<nav class="bottom-nav">
  <a href="/map" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_map.png') ?>" alt="Map" /><span data-i18n-key="nav_map">マップ</span></a>
  <a href="/dify" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_ai.png') ?>" alt="AI" /><span data-i18n-key="nav_ai">AI</span></a>
  <a href="/favorites" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_favorite.png') ?>" alt="お気に入り" /><span data-i18n-key="nav_favorites">お気に入り</span></a>
  <a href="/magazine" class="nav-item active"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_magazine.png') ?>" alt="雑誌" /><span data-i18n-key="nav_magazine">雑誌</span></a>
  <a href="/profile" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_profile.png') ?>" alt="マイページ" /><span data-i18n-key="nav_profile">マイページ</span></a>
</nav>
