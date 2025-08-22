<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-auth">
  <h2>Sign up</h2>
  <form id="roro-signup-form">
    <label>メール<input type="email" name="email" required></label>
    <label>パスワード<input type="password" name="password" minlength="8" required></label>
    <button type="submit">登録</button>
  </form>
  <p><a href="<?php echo esc_url( site_url('/login/') ); ?>">ログインへ戻る</a></p>
</div>
