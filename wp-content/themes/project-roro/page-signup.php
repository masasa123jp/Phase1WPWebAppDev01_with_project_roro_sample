<?php
/* Template Name: 新規登録ページ */
get_header();
?>
<header>
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="Project RORO ロゴ" class="logo" />
</header>
<main class="signup-container">
  <h1 data-i18n-key="signup_title">新規登録</h1>
  <form id="signup-form" autocomplete="off">
    <div class="input-group">
      <label for="name" data-i18n-key="label_name">お名前</label>
      <input type="text" id="name" placeholder="山田太郎" required />
    </div>
    <div class="input-group">
      <label for="furigana" data-i18n-key="label_furigana">ふりがな</label>
      <input type="text" id="furigana" placeholder="やまだたろう" />
    </div>
    <div class="input-group">
      <label for="email" data-i18n-key="label_email">メールアドレス</label>
      <input type="email" id="email" placeholder="sample@example.com" required />
    </div>
    <div class="input-group">
      <label for="password" data-i18n-key="label_password">パスワード</label>
      <input type="password" id="password" placeholder="半角英数6文字以上" required />
    </div>
    <div class="input-group">
      <label for="passwordConfirm" data-i18n-key="label_password_confirm">パスワード（確認）</label>
      <input type="password" id="passwordConfirm" placeholder="確認用パスワード" required />
    </div>
    <div class="input-group">
      <label for="petType" data-i18n-key="label_pet_type">ペットの種類</label>
      <select id="petType">
        <option value="dog" data-i18n-key="dog">犬</option>
        <option value="cat" data-i18n-key="cat">猫</option>
      </select>
    </div>
    <div class="input-group">
      <label for="petName" data-i18n-key="label_pet_name">ペットのお名前</label>
      <input type="text" id="petName" placeholder="ぽち" />
    </div>
    <div class="input-group">
      <label for="petAge" data-i18n-key="label_pet_age">ペットの年齢</label>
      <select id="petAge">
        <option value="puppy" data-i18n-key="puppy">子犬/子猫 (1歳未満)</option>
        <option value="adult" data-i18n-key="adult">成犬/成猫 (1〜7歳)</option>
        <option value="senior" data-i18n-key="senior">シニア犬/シニア猫 (7歳以上)</option>
      </select>
    </div>
    <div class="input-group">
      <label for="address" data-i18n-key="label_address">住所</label>
      <input type="text" id="address" placeholder="東京都港区…" />
    </div>
    <div class="input-group">
      <label for="phone" data-i18n-key="label_phone">電話番号</label>
      <input type="tel" id="phone" placeholder="09012345678" />
    </div>
    <button type="submit" class="btn primary-btn" data-i18n-key="signup_submit">新規登録</button>
  </form>
  <div class="social-login">
    <button type="button" class="btn google-btn" data-i18n-key="signup_google">Googleで登録</button>
    <button type="button" class="btn line-btn" data-i18n-key="signup_line">LINEで登録</button>
  </div>
  <p>
    <span data-i18n-key="have_account">すでにアカウントをお持ちの方は</span>
    <a href="<?php echo esc_url(home_url('/')); ?>" data-i18n-key="go_login">こちらからログイン</a>
  </p>
</main>
<?php get_footer(); ?>
