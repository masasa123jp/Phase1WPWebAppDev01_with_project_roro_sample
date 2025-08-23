<?php
/**
 * AIアシスタント画面テンプレート
 */
?>
<header class="app-header">
  <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png') ?>" alt="logo" class="small-logo" />
  <h2 data-i18n-key="ai_title">AIアシスタント</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="言語切替">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/switch-language.png') ?>" alt="Language" />
  </button>
</header>

<main class="dify-container">
  <p data-i18n-key="ai_intro">AIアシスタントにペットの気になることを気軽に質問してみましょう。</p>

  <section id="embed-area" class="panel">
    <div id="embed-host" class="external-chat-container"></div>
  </section>

  <section id="custom-area" class="panel">
    <h3 class="section-title">オリジナル UI（Dify 風）</h3>
    <div id="custom-host"></div>
    <div class="note">/api/chat を用意するとストリーミング表示します。</div>
  </section>
</main>

<nav class="bottom-nav">
  <a href="/map" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_map.png') ?>" alt="Map" /><span data-i18n-key="nav_map">マップ</span></a>
  <a href="/dify" class="nav-item active"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_ai.png') ?>" alt="AI" /><span data-i18n-key="nav_ai">AI</span></a>
  <a href="/favorites" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_favorite.png') ?>" alt="お気に入り" /><span data-i18n-key="nav_favorites">お気に入り</span></a>
  <a href="/magazine" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_magazine.png') ?>" alt="雑誌" /><span data-i18n-key="nav_magazine">雑誌</span></a>
  <a href="/profile" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_profile.png') ?>" alt="マイページ" /><span data-i18n-key="nav_profile">マイページ</span></a>
</nav>
