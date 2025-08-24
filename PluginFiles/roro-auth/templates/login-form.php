<?php
/**
 * Login form template
 */
global $roro_auth_messages;
?>
<div class="roro-auth-form roro-auth-login">
  <h2><?php echo esc_html($roro_auth_messages['login_title']); ?></h2>

  <?php if ( !empty($_SESSION['roro_auth_error']) ) : ?>
    <div class="roro-auth-alert roro-auth-error"><?php
      echo nl2br( esc_html($_SESSION['roro_auth_error']) );
      unset($_SESSION['roro_auth_error']);
    ?></div>
  <?php endif; ?>

  <?php if ( !empty($_SESSION['roro_auth_success']) ) : ?>
    <div class="roro-auth-alert roro-auth-success"><?php
      echo nl2br( esc_html($_SESSION['roro_auth_success']) );
      unset($_SESSION['roro_auth_success']);
    ?></div>
  <?php endif; ?>

  <form method="post" class="roro-auth-form-inner" autocomplete="on">
    <div class="roro-auth-field">
      <label for="roro-login-username">
        <?php echo esc_html($roro_auth_messages['username']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-login-username" type="text" name="log"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_username']); ?>"
             required>
    </div>

    <div class="roro-auth-field">
      <label for="roro-login-password">
        <?php echo esc_html($roro_auth_messages['password']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-login-password" type="password" name="pwd"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_password']); ?>"
             required>
    </div>

    <div class="roro-auth-field roro-auth-remember">
      <label>
        <input type="checkbox" name="rememberme" value="1">
        <?php echo esc_html($roro_auth_messages['remember_me']); ?>
      </label>
    </div>

    <?php wp_nonce_field('roro_auth_login', 'roro_auth_nonce'); ?>
    <input type="hidden" name="roro_auth_action" value="login">

    <div class="roro-auth-actions">
      <button type="submit" class="roro-auth-btn"><?php echo esc_html($roro_auth_messages['login_button']); ?></button>
    </div>
  </form>

  <div class="roro-auth-switch">
    <small><?php echo esc_html($roro_auth_messages['no_account']); ?></small>
  </div>

  <div class="roro-auth-social-section">
    <div class="roro-auth-sep"><span><?php echo esc_html($roro_auth_messages['or']); ?></span></div>
    <?php echo do_shortcode('[roro_social_login]'); ?>
  </div>
</div>
