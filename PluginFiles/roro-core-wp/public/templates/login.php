<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-auth">
  <h2>Login</h2>
  <form id="roro-login-form">
    <label>ユーザー名 / メール<input type="text" name="login" required></label>
    <label>パスワーグ<input type="password" name="password" required></label>
    <button type="submit">ログイン</button>
  </form>
  <p><a href="<?php echo esc_url( site_url('/signup/') ); ?>">新規登録はこちら</a></p>
</div>
