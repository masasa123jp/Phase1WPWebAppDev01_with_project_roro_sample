<?php
/**
 * ログイン画面テンプレート
 * WordPressの固定ページにショートコード [roro_login] を貼ると表示されます。
 */
?>
<header class="app-header center">
  <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png') ?>" alt="Project RORO ロゴ" class="logo" />
</header>

<main class="login-container">
  <div class="login-header">
    <h1 data-i18n-key="login_greeting">こんにちは！</h1>
    <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
      <img src="<?= esc_url(RORO_CORE_WP_URL.'assets/images/switch-language.png') ?>" alt="Language" />
    </button>
  </div>

  <form id="login-form" autocomplete="off" class="panel">
    <div class="input-group">
      <label for="login-email" data-i18n-key="login_email">メールアドレス</label>
      <input type="email" id="login-email" placeholder="sample@example.com" required />
    </div>
    <div class="input-group">
      <label for="login-password" data-i18n-key="login_password">パスワード</label>
      <input type="password" id="login-password" placeholder="パスワード" required />
    </div>
    <button type="submit" class="btn primary-btn" data-i18n-key="login_submit">ログイン</button>
  </form>

  <div class="social-login">
    <button type="button" class="btn google-btn" data-i18n-key="login_google">Googleでログイン</button>
    <button type="button" class="btn line-btn" data-i18n-key="login_line">LINEでログイン</button>
  </div>

  <p class="center">
    <span data-i18n-key="login_no_account">アカウントをお持ちでない場合は</span>
    <a href="<?= esc_url( site_url('/signup') ) ?>" data-i18n-key="login_register_link">こちらから新規登録</a>
  </p>
</main>
