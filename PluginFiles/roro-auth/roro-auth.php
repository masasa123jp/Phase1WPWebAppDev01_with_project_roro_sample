<?php
/**
 * Plugin Name: Roro Auth
 * Description: ソーシャル連携の解除UI強化、メール通知テンプレート最適化、プロフィール画像アップロード。
 * Version: 1.6.0
 * Requires at least: 6.3
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Text Domain: roro-auth
 */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/includes/class-roro-auth-social.php';
require_once __DIR__ . '/includes/class-roro-auth-notifier.php';

// プロフィール画像：ショートコード [roro_profile_avatar_form]
add_shortcode('roro_profile_avatar_form', function($atts){
    if (!is_user_logged_in()) {
        return '<p>ログインしてください。</p>';
    }
    $nonce = wp_create_nonce('roro_profile_avatar');
    ob_start(); ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="roro_profile_avatar_nonce" value="<?php echo esc_attr($nonce); ?>"/>
        <p><input type="file" name="roro_profile_avatar" accept="image/*"/></p>
        <p><button class="button button-primary">プロフィール画像を更新</button></p>
    </form>
    <?php
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['roro_profile_avatar_nonce'])) {
        if (!wp_verify_nonce($_POST['roro_profile_avatar_nonce'], 'roro_profile_avatar')) {
            echo '<div class="notice notice-error"><p>不正なリクエストです。</p></div>';
        } else {
            $uid = get_current_user_id();
            if (!empty($_FILES['roro_profile_avatar']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $att_id = media_handle_upload('roro_profile_avatar', 0);
                if (!is_wp_error($att_id)) {
                    update_user_meta($uid, 'roro_profile_photo_id', $att_id);
                    echo '<div class="notice notice-success"><p>プロフィール画像を更新しました。</p></div>';
                    // RORO_CUSTOMER.photo_attachment_id へも同期（存在すれば）
                    global $wpdb;
                    $link_cust = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d", $uid));
                    if ($link_cust) {
                        $wpdb->update('RORO_PET', ['photo_attachment_id' => intval($att_id)], ['customer_id' => intval($link_cust)]);
                    }
                } else {
                    echo '<div class="notice notice-error"><p>アップロードに失敗しました: '.esc_html($att_id->get_error_message()).'</p></div>';
                }
            }
        }
    }
    return ob_get_clean();
});
