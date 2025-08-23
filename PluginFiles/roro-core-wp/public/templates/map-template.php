<?php
/**
 * マップページテンプレート
 * Google Mapsを利用し、イベントマーカーを表示します。
 */
?>
<header class="app-header">
  <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png') ?>" alt="logo" class="small-logo" />
  <h2 data-i18n-key="map_title">おでかけマップ</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/switch-language.png') ?>" alt="Language" />
  </button>
</header>

<main id="map-container">
  <div id="category-bar" class="category-bar"></div>
  <div id="map" style="width:100%;height:60vh;"></div>
  <button id="reset-view-btn" class="reset-btn" data-i18n-key="reset_view">周辺表示</button>
</main>

<nav class="bottom-nav">
  <a href="/map" class="nav-item active"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_map.png') ?>" alt="Map" /><span data-i18n-key="nav_map">マップ</span></a>
  <a href="/dify" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_ai.png') ?>" alt="AI" /><span data-i18n-key="nav_ai">AI</span></a>
  <a href="/favorites" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_favorite.png') ?>" alt="お気に入り" /><span data-i18n-key="nav_favorites">お気に入り</span></a>
  <a href="/magazine" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_magazine.png') ?>" alt="雑誌" /><span data-i18n-key="nav_magazine">雑誌</span></a>
  <a href="/profile" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_profile.png') ?>" alt="マイページ" /><span data-i18n-key="nav_profile">マイページ</span></a>
</nav>
