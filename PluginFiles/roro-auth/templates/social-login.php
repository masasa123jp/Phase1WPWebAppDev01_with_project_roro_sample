<?php
/**
 * Social login buttons template (Google / LINE)
 */
global $roro_auth_messages;

$google_login_url = function_exists('roro_auth_get_google_auth_url')
    ? roro_auth_get_google_auth_url()
    : '#';

$line_login_url = function_exists('roro_auth_get_line_auth_url')
    ? roro_auth_get_line_auth_url()
    : '#';
?>
<div class="roro-auth-social-login">
  <a class="roro-auth-btn roro-auth-btn-google" href="<?php echo esc_url($google_login_url); ?>">
    <?php echo esc_html($roro_auth_messages['login_with_google']); ?>
  </a>
  <a class="roro-auth-btn roro-auth-btn-line" href="<?php echo esc_url($line_login_url); ?>">
    <?php echo esc_html($roro_auth_messages['login_with_line']); ?>
  </a>
</div>
