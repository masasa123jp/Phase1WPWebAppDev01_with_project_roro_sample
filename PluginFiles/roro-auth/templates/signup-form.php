<?php
/**
 * Signup form template
 */
global $roro_auth_messages;
?>
<div class="roro-auth-form roro-auth-signup">
  <h2><?php echo esc_html($roro_auth_messages['signup_title']); ?></h2>

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
      <label for="roro-signup-username">
        <?php echo esc_html($roro_auth_messages['username']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-signup-username" type="text" name="user_login"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_username']); ?>"
             required>
    </div>

    <div class="roro-auth-field">
      <label for="roro-signup-email">
        <?php echo esc_html($roro_auth_messages['email']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-signup-email" type="email" name="user_email"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_email']); ?>"
             required>
    </div>

    <div class="roro-auth-field">
      <label for="roro-signup-pass">
        <?php echo esc_html($roro_auth_messages['password']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-signup-pass" type="password" name="user_pass"
             minlength="8"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_password']); ?>"
             required>
    </div>

    <div class="roro-auth-field">
      <label for="roro-signup-pass2">
        <?php echo esc_html($roro_auth_messages['password_confirm']); ?>
        <span class="roro-auth-req">*</span>
      </label>
      <input id="roro-signup-pass2" type="password" name="user_pass_confirm"
             minlength="8"
             placeholder="<?php echo esc_attr($roro_auth_messages['placeholder_password_confirm']); ?>"
             required>
    </div>

    <div class="roro-auth-field roro-auth-terms">
      <label>
        <input type="checkbox" name="agree_terms" value="1" required>
        <?php echo esc_html($roro_auth_messages['agree_terms']); ?>
      </label>
    </div>

    <?php wp_nonce_field('roro_auth_signup', 'roro_auth_nonce'); ?>
    <input type="hidden" name="roro_auth_action" value="signup">

    <div class="roro-auth-actions">
      <button type="submit" class="roro-auth-btn"><?php echo esc_html($roro_auth_messages['signup_button']); ?></button>
    </div>
  </form>

  <div class="roro-auth-switch">
    <small><?php echo esc_html($roro_auth_messages['have_account']); ?></small>
  </div>

  <div class="roro-auth-social-section">
    <div class="roro-auth-sep"><span><?php echo esc_html($roro_auth_messages['or']); ?></span></div>
    <?php echo do_shortcode('[roro_social_login]'); ?>
  </div>
</div>
