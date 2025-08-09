<?php
/* Template Name: マイページ */
get_header();
?>
<header class="app-header">
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="ロゴ" class="small-logo" />
  <h2 data-i18n-key="profile_title">マイページ</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/switch-language.png" alt="Language" />
  </button>
</header>
<main class="profile-container">
  <div class="profile-card">
    <div class="avatar"></div>
    <h3 id="profile-name"></h3>
    <p id="profile-location"></p>
    <div class="stats">
      <div><strong id="fav-count">0</strong><span data-i18n-key="favorites">お気に入り</span></div>
      <div><strong id="followers">0</strong><span data-i18n-key="followers">フォロワー</span></div>
      <div><strong id="following">0</strong><span data-i18n-key="following">フォロー中</span></div>
    </div>
  </div>
  <form id="profile-form" autocomplete="off">
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
      </select>
    </div>
    <h4 data-i18n-key="pet_info">ペット情報</h4>
    <div id="pets-container"></div>
    <button type="button" id="add-pet-btn" class="btn secondary-btn" style="margin-bottom: 1rem;" data-i18n-key="add_pet">ペットを追加</button>
    <button type="submit" class="btn primary-btn" data-i18n-key="save">保存</button>
  </form>
  <button id="logout-btn" class="btn danger-btn" style="margin-top: 1rem;" data-i18n-key="logout">ログアウト</button>
</main>
<?php get_footer(); ?>
