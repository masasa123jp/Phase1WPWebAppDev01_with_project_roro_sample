<?php
/**
 * マイページ画面テンプレート
 */
?>
<header class="app-header">
  <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png') ?>" alt="logo" class="small-logo" />
  <h2 data-i18n-key="profile_title">マイページ</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="言語切替">
    <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/switch-language.png') ?>" alt="Language" />
  </button>
</header>

<main class="profile-container">
  <div class="profile-card panel">
    <div class="avatar"></div>
    <h3 id="profile-name"></h3>
    <p id="profile-location"></p>
    <div class="stats">
      <div><strong id="fav-count">0</strong><span>お気に入り</span></div>
      <div><strong id="followers">0</strong><span>フォロワー</span></div>
      <div><strong id="following">0</strong><span>フォロー中</span></div>
    </div>
  </div>

  <form id="profile-form" autocomplete="off" class="panel">
    <h4 data-i18n-key="profile_edit">プロフィール編集</h4>
    <div class="input-group">
      <label for="profile-name-input" data-i18n-key="label_name">お名前</label>
      <input type="text" id="profile-name-input" disabled />
    </div>
    <div class="input-group">
      <label for="profile-furigana-input" data-i18n-key="label_furigana">ふりがな</label>
      <input type="text" id="profile-furigana-input" disabled />
    </div>
    <div class="input-group">
      <label for="profile-email" data-i18n-key="label_email">メールアドレス</label>
      <input type="email" id="profile-email" />
    </div>
    <div class="input-group">
      <label for="profile-phone" data-i18n-key="label_phone">電話番号</label>
      <input type="tel" id="profile-phone" />
    </div>
    <div class="input-group">
      <label for="profile-address" data-i18n-key="label_address">住所</label>
      <input type="text" id="profile-address" />
    </div>
    <div class="input-group">
      <label for="profile-language" data-i18n-key="label_language">言語</label>
      <select id="profile-language">
        <option value="ja">日本語</option>
        <option value="en">English</option>
        <option value="zh">中文</option>
        <option value="ko">한국어</option>
      </select>
    </div>

    <h4 data-i18n-key="pet_info">ペット情報</h4>
    <div id="pets-container"></div>
    <button type="button" id="add-pet-btn" class="btn secondary-btn" style="margin-bottom: 1rem;" data-i18n-key="add_pet">ペットを追加</button>
    <button type="submit" class="btn primary-btn" data-i18n-key="save">保存</button>
  </form>
  <button id="logout-btn" class="btn danger-btn" style="margin-top: 1rem;" data-i18n-key="logout">ログアウト</button>
</main>

<nav class="bottom-nav">
  <a href="/map" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_map.png') ?>" alt="Map" /><span data-i18n-key="nav_map">マップ</span></a>
  <a href="/dify" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_ai.png') ?>" alt="AI" /><span data-i18n-key="nav_ai">AI</span></a>
  <a href="/favorites" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_favorite.png') ?>" alt="お気に入り" /><span data-i18n-key="nav_favorites">お気に入り</span></a>
  <a href="/magazine" class="nav-item"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_magazine.png') ?>" alt="雑誌" /><span data-i18n-key="nav_magazine">雑誌</span></a>
  <a href="/profile" class="nav-item active"><img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/icon_profile.png') ?>" alt="マイページ" /><span data-i18n-key="nav_profile">マイページ</span></a>
</nav>
