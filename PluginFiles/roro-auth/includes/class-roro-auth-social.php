<?php
// roro-auth/includes/class-roro-auth-social.php
if (!defined('ABSPATH')) { exit; }

class Roro_Auth_Social {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_roro_unlink_social', [__CLASS__, 'handle_unlink']);
    }
    public static function menu() {
        add_users_page('Roro Social', 'Roro Social', 'read', 'roro-social', [__CLASS__, 'render']);
    }
    public static function render() {
        if (!is_user_logged_in()) { wp_die('ログインしてください。'); }
        $uid = get_current_user_id();
        global $wpdb;
        // 現在の顧客ID
        $customer_id = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d", $uid));
        $rows = [];
        if ($customer_id) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT account_id, provider, provider_user_id, email, email_verified, status, created_at, last_login_at FROM RORO_AUTH_ACCOUNT WHERE customer_id=%d ORDER BY provider", $customer_id), ARRAY_A);
        }
        // 連携数/ローカルあり判定
        $linked_count = is_array($rows) ? count($rows) : 0;
        $has_local = false;
        foreach ($rows as $r) if ($r['provider']==='local') { $has_local = true; break; }
        $nonce = wp_create_nonce('roro_unlink');
        echo '<div class="wrap"><h1>ソーシャル連携</h1>';
        if (!$customer_id) {
            echo '<p>このWPユーザーは RORO_CUSTOMER に未リンクです。</p></div>'; return;
        }
        echo '<table class="wp-list-table widefat striped"><thead><tr><th>Provider</th><th>Account</th><th>Email</th><th>Verified</th><th>Status</th><th>操作</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $disabled = '';
            $warn = '';
            // 唯一の連携なら誤解除を抑止
            if ($linked_count <= 1) { $disabled = ' disabled '; $warn = '（唯一の連携のため解除不可）'; }
            // ローカルが無く、provider=google等のみ1個のケースも抑止
            if (!$has_local && $linked_count == 1) { $disabled = ' disabled '; $warn = '（唯一の連携のため解除不可）'; }
            $unlink_url = admin_url('admin-post.php?action=roro_unlink_social&account_id='.(int)$r['account_id'].'&_wpnonce='.$nonce);
            echo '<tr>';
            echo '<td>'.esc_html($r['provider']).'</td>';
            echo '<td>'.esc_html($r['provider_user_id']).'</td>';
            echo '<td>'.esc_html($r['email']).'</td>';
            echo '<td>'.(intval($r['email_verified']) ? 'Yes' : 'No').'</td>';
            echo '<td>'.esc_html($r['status']).'</td>';
            echo '<td><a class="button'.($disabled?' disabled':'').'" href="'.esc_url($unlink_url).'" '.($disabled?'aria-disabled="true"':'').'>';
            echo '連携解除</a> '.$warn.'</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    public static function handle_unlink() {
        if (!is_user_logged_in()) { wp_die('ログインしてください。'); }
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'roro_unlink')) wp_die('不正なリクエスト');
        $uid = get_current_user_id();
        $account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
        if (!$account_id) wp_die('account_idが不正です。');
        global $wpdb;
        $customer_id = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d", $uid));
        if (!$customer_id) wp_die('リンクされていません。');
        // 連携件数と唯一チェック
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM RORO_AUTH_ACCOUNT WHERE customer_id=%d", $customer_id));
        $has_local = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM RORO_AUTH_ACCOUNT WHERE customer_id=%d AND provider='local'", $customer_id));
        if ($count <= 1 || (!$has_local and $count == 1)) {
            wp_die('唯一の連携のため解除できません。');
        }
        // 所有権チェック
        $owner = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM RORO_AUTH_ACCOUNT WHERE account_id=%d AND customer_id=%d", $account_id, $customer_id));
        if (!$owner) wp_die('対象が見つかりません。');
        // 論理解除（status変更）
        $wpdb->update('RORO_AUTH_ACCOUNT', ['status'=>'deleted'], ['account_id'=>$account_id]);
        // 通知
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM RORO_CUSTOMER WHERE customer_id=%d", $customer_id));
        if ($email) {
            $subj = apply_filters('roro_auth_mail_subject', '【RORO】ソーシャル連携を解除しました', 'unlink');
            $body = apply_filters('roro_auth_mail_body', Roro_Auth_Notifier::template('unlink', ['account_id'=>$account_id]), 'unlink');
            wp_mail($email, $subj, $body);
        }
        wp_redirect(add_query_arg(['page'=>'roro-social','unlinked'=>1], admin_url('users.php'))); exit;
    }
}
Roro_Auth_Social::init();
