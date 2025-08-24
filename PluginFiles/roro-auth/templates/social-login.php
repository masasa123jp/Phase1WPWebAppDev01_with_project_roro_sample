<?php
if (!defined('ABSPATH')) exit;
/** @var array $messages */
/** @var array $settings */
/** @var array $enabled */
/** @var array $atts */
$redirect_to = isset($atts['redirect_to']) ? esc_url($atts['redirect_to']) : home_url('/');
$flash = RORO_Auth_Utils::consume_flash();
?>
<div class="roro-auth-wrap">
  <?php if ($flash): ?>
    <div class="roro-auth-flash roro-auth-<?php echo esc_attr($flash['type']); ?>">
      <?php echo esc_html($flash['message']); ?>
    </div>
  <?php endif; ?>

  <h3 class="roro-auth-title"><?php echo esc_html($messages['social_login_title']); ?></h3>
  <p class="roro-auth-subtitle"><?php echo esc_html($messages['social_login_sub']); ?></p>

  <div class="roro-auth-btns">
    <?php if (!empty($enabled['google'])): ?>
      <a class="roro-btn roro-google" href="<?php echo esc_url(add_query_arg(['roro_auth'=>'login','provider'=>'google','redirect_to'=>$redirect_to], home_url('/'))); ?>">
        <?php echo esc_html($messages['login_with_google']); ?>
      </a>
    <?php endif; ?>

    <?php if (!empty($enabled['line'])): ?>
      <a class="roro-btn roro-line" href="<?php echo esc_url(add_query_arg(['roro_auth'=>'login','provider'=>'line','redirect_to'=>$redirect_to], home_url('/'))); ?>">
        <?php echo esc_html($messages['login_with_line']); ?>
      </a>
    <?php endif; ?>

    <?php if ($atts['show_apple'] === 'yes'): ?>
      <button class="roro-btn roro-apple" type="button" disabled><?php echo esc_html($messages['login_with_apple']); ?> (<?php echo esc_html($messages['not_implemented']); ?>)</button>
    <?php endif; ?>

    <?php if ($atts['show_fb'] === 'yes'): ?>
      <button class="roro-btn roro-facebook" type="button" disabled><?php echo esc_html($messages['login_with_facebook']); ?> (<?php echo esc_html($messages['not_implemented']); ?>)</button>
    <?php endif; ?>
  </div>

  <?php if ($atts['show_wp'] === 'yes'): ?>
    <div class="roro-auth-divider"><span><?php echo esc_html($messages['or']); ?></span></div>
    <p><a href="<?php echo esc_url(wp_login_url($redirect_to)); ?>"><?php echo esc_html($messages['login_with_wp']); ?></a></p>
  <?php endif; ?>
</div>
