<?php
if (!defined('ABSPATH')) exit;
$redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : home_url('/');
$flash = RORO_Auth_Utils::consume_flash();
$messages = RORO_Auth_Utils::messages();
?>
<div class="roro-auth-wrap">
  <?php if ($flash): ?>
    <div class="roro-auth-flash roro-auth-<?php echo esc_attr($flash['type']); ?>">
      <?php echo esc_html($flash['message']); ?>
    </div>
  <?php endif; ?>

  <h3 class="roro-auth-title"><?php echo esc_html($messages['login_title']); ?></h3>
  <form action="<?php echo esc_url(wp_login_url()); ?>" method="post" class="roro-auth-form">
    <p>
      <label><?php echo esc_html($messages['email']); ?></label>
      <input type="text" name="log" required>
    </p>
    <p>
      <label><?php echo esc_html($messages['password']); ?></label>
      <input type="password" name="pwd" required>
    </p>
    <p>
      <label><input type="checkbox" name="rememberme" value="forever"> <?php echo esc_html($messages['remember_me']); ?></label>
    </p>
    <p>
      <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
      <button class="roro-btn" type="submit"><?php echo esc_html($messages['login_button']); ?></button>
    </p>
  </form>
</div>
