<?php
/**
 * Plugin Name: RORO Auth
 * Description: Project RORO 用ユーザー認証プラグイン（メール/パスワード & Google/LINEログイン対応、カスタムテーブル連携、I18N対応）
 * Version: 1.0.1
 * Author: Project RORO Team
 */

if ( !defined('ABSPATH') ) {
    exit; // 直接アクセス禁止
}

/* ==========================================================
 * 定数
 * ========================================================== */
define('RORO_AUTH_VERSION',            '1.0.1');
define('RORO_AUTH_CUSTOMER_TABLE',     'roro_customer'); // 実テーブル名は $wpdb->prefix を付与
define('RORO_AUTH_DIR',                plugin_dir_path(__FILE__));
define('RORO_AUTH_URL',                plugin_dir_url(__FILE__));

/* ==========================================================
 * セッション（テンプレ表示用メッセージ保持）
 * ========================================================== */
if ( ! function_exists('roro_auth_start_session') ) {
    function roro_auth_start_session() {
        if ( PHP_SESSION_NONE === session_status() ) {
            // 注意: WPではセッションを極力避けるが、本プラグインでは
            // 画面間メッセージ表示のため最小限で利用
            session_start();
        }
    }
}
add_action('init', 'roro_auth_start_session', 1);

/* ==========================================================
 * 多言語メッセージの読み込み
 * ========================================================== */
global $roro_auth_messages;

function roro_auth_load_language( $locale = '' ) {
    global $roro_auth_messages;
    if ( empty($locale) ) {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    }
    $lang_code = substr($locale, 0, 2);   // 'ja_JP' -> 'ja'
    $lang_file = RORO_AUTH_DIR . "lang/{$lang_code}.php";
    if ( file_exists($lang_file) ) {
        include $lang_file; // 各ファイル内で $roro_auth_messages を定義
    } else {
        include RORO_AUTH_DIR . "lang/en.php";
    }
}
roro_auth_load_language();

/* ==========================================================
 * 有効化フック：テーブル作成 / オプション初期化
 * ========================================================== */
register_activation_hook(__FILE__, 'roro_auth_activate');
function roro_auth_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . RORO_AUTH_CUSTOMER_TABLE;
    $charset    = $wpdb->get_charset_collate();

    // 存在しない場合のみ作成
    if ( $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $table_name
        ) ) !== $table_name ) {
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_idx (user_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    add_option('roro_auth_google_client_id', '');
    add_option('roro_auth_google_client_secret', '');
    add_option('roro_auth_line_client_id', '');
    add_option('roro_auth_line_client_secret', '');
}

/* ==========================================================
 * アセット
 * ========================================================== */
add_action('wp_enqueue_scripts', 'roro_auth_enqueue_assets');
function roro_auth_enqueue_assets() {
    wp_enqueue_style('roro-auth-style', RORO_AUTH_URL . 'assets/style.css', array(), RORO_AUTH_VERSION);
}

/* ==========================================================
 * ショートコード
 * ========================================================== */
add_shortcode('roro_login',        'roro_auth_login_form_shortcode');
add_shortcode('roro_signup',       'roro_auth_signup_form_shortcode');
add_shortcode('roro_social_login', 'roro_auth_social_login_shortcode');

function roro_auth_login_form_shortcode($atts = [], $content = null) {
    ob_start();
    include RORO_AUTH_DIR . 'templates/login-form.php';
    return ob_get_clean();
}
function roro_auth_signup_form_shortcode($atts = [], $content = null) {
    ob_start();
    include RORO_AUTH_DIR . 'templates/signup-form.php';
    return ob_get_clean();
}
function roro_auth_social_login_shortcode($atts = [], $content = null) {
    ob_start();
    include RORO_AUTH_DIR . 'templates/social-login.php';
    return ob_get_clean();
}

/* ==========================================================
 * フォーム送信ハンドラ
 * ========================================================== */
add_action('init', 'roro_auth_handle_form_submission', 20);
function roro_auth_handle_form_submission() {
    global $roro_auth_messages;

    if ( 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['roro_auth_action']) ) {
        $action = sanitize_text_field( $_POST['roro_auth_action'] );

        // Nonce 検証
        if ( !isset($_POST['roro_auth_nonce']) ||
             !wp_verify_nonce($_POST['roro_auth_nonce'], 'roro_auth_' . $action) ) {
            $_SESSION['roro_auth_error'] = $roro_auth_messages['error_nonce'] ?? 'Invalid request.';
            roro_auth_redirect_back();
        }

        if ( $action === 'login' ) {
            $creds = array(
                'user_login'    => sanitize_text_field( $_POST['log'] ?? '' ),
                'user_password' => $_POST['pwd'] ?? '',
                'remember'      => !empty($_POST['rememberme']),
            );
            $user = wp_signon($creds, false);
            if ( is_wp_error($user) ) {
                $_SESSION['roro_auth_error'] = $roro_auth_messages['error_login_failed'] ?? $user->get_error_message();
            } else {
                $_SESSION['roro_auth_success'] = $roro_auth_messages['success_login'] ?? 'Signed in.';
                wp_safe_redirect( home_url() );
                exit;
            }
            roro_auth_redirect_back();
        }

        if ( $action === 'signup' ) {
            $username   = sanitize_user( $_POST['user_login'] ?? '' );
            $email      = sanitize_email( $_POST['user_email'] ?? '' );
            $password   = (string)($_POST['user_pass'] ?? '');
            $pass_conf  = (string)($_POST['user_pass_confirm'] ?? '');
            $agree      = !empty($_POST['agree_terms']);

            $errors = array();
            if ( empty($username) || empty($email) || empty($password) ) {
                $errors[] = $roro_auth_messages['error_required'];
            }
            if ( !empty($email) && !is_email($email) ) {
                $errors[] = $roro_auth_messages['error_invalid_email'];
            }
            if ( strlen($password) < 8 ) {
                $errors[] = $roro_auth_messages['error_password_short'];
            }
            if ( $password !== $pass_conf ) {
                $errors[] = $roro_auth_messages['error_password_mismatch'];
            }
            if ( !$agree ) {
                $errors[] = $roro_auth_messages['error_terms_unchecked'];
            }
            if ( username_exists($username) ) {
                $errors[] = $roro_auth_messages['error_username_exists'];
            }
            if ( email_exists($email) ) {
                $errors[] = $roro_auth_messages['error_email_exists'];
            }

            if ( !empty($errors) ) {
                $_SESSION['roro_auth_error'] = implode("\n", array_filter($errors));
                roro_auth_redirect_back();
            }

            // ユーザー作成
            $user_id = wp_create_user($username, $password, $email);
            if ( is_wp_error($user_id) ) {
                $_SESSION['roro_auth_error'] = $user_id->get_error_message();
                roro_auth_redirect_back();
            }

            // カスタムテーブル登録
            roro_auth_link_user_to_customer($user_id);

            // 自動ログイン
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            $_SESSION['roro_auth_success'] = $roro_auth_messages['success_signup'] ?? 'Account created.';
            wp_safe_redirect( home_url() );
            exit;
        }
    }
}

function roro_auth_redirect_back() {
    $ref = wp_get_referer();
    if ( !$ref ) { $ref = home_url(); }
    wp_safe_redirect( remove_query_arg(array('code','state'), $ref) );
    exit;
}

/* ==========================================================
 * ソーシャルログイン（OAuth 2.0）
 * ========================================================== */
function roro_auth_get_google_auth_url() {
    $client_id = get_option('roro_auth_google_client_id');
    $redirect  = home_url('/'); // 必要に応じ専用コールバックURLへ変更
    $state     = wp_create_nonce('roro_auth_google');
    $scope     = rawurlencode('email profile');
    return "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id={$client_id}&redirect_uri={$redirect}&scope={$scope}&state={$state}";
}
function roro_auth_get_line_auth_url() {
    $client_id = get_option('roro_auth_line_client_id');
    $redirect  = home_url('/');
    $state     = wp_create_nonce('roro_auth_line');
    $scope     = rawurlencode('profile openid email');
    return "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect}&scope={$scope}&state={$state}";
}

add_action('init', 'roro_auth_handle_oauth_callback', 15);
function roro_auth_handle_oauth_callback() {
    global $roro_auth_messages;

    if ( isset($_GET['code'], $_GET['state']) ) {

        // --- Google ---
        if ( wp_verify_nonce( sanitize_text_field($_GET['state']), 'roro_auth_google' ) ) {
            $code = sanitize_text_field($_GET['code']);
            $resp = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'timeout' => 15,
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => get_option('roro_auth_google_client_id'),
                    'client_secret' => get_option('roro_auth_google_client_secret'),
                    'redirect_uri'  => home_url('/'),
                    'grant_type'    => 'authorization_code',
                ),
            ));
            if ( is_wp_error($resp) ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_token']; roro_auth_redirect_back(); }
            $data = json_decode( wp_remote_retrieve_body($resp), true );
            $token = $data['access_token'] ?? '';
            if ( !$token ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_token']; roro_auth_redirect_back(); }

            $u = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . rawurlencode($token) );
            if ( is_wp_error($u) ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_profile']; roro_auth_redirect_back(); }
            $profile = json_decode( wp_remote_retrieve_body($u), true );
            $email   = $profile['email'] ?? '';
            $name    = $profile['name'] ?? ($profile['given_name'] ?? '');

            if ( !$email ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_email_missing']; roro_auth_redirect_back(); }

            $user = get_user_by('email', $email);
            if ( !$user ) {
                $uid = wp_create_user( $email, wp_generate_password(), $email );
                if ( is_wp_error($uid) ) { $_SESSION['roro_auth_error'] = $uid->get_error_message(); roro_auth_redirect_back(); }
                roro_auth_link_user_to_customer($uid);
                $user = get_user_by('ID', $uid);
            }
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            wp_safe_redirect( home_url() );
            exit;
        }

        // --- LINE ---
        if ( wp_verify_nonce( sanitize_text_field($_GET['state']), 'roro_auth_line' ) ) {
            $code = sanitize_text_field($_GET['code']);
            $resp = wp_remote_post('https://api.line.me/oauth2/v2.1/token', array(
                'timeout' => 15,
                'body'    => array(
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => home_url('/'),
                    'client_id'     => get_option('roro_auth_line_client_id'),
                    'client_secret' => get_option('roro_auth_line_client_secret'),
                ),
            ));
            if ( is_wp_error($resp) ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_token']; roro_auth_redirect_back(); }
            $data  = json_decode( wp_remote_retrieve_body($resp), true );
            $token = $data['access_token'] ?? '';
            if ( !$token ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_token']; roro_auth_redirect_back(); }

            $u = wp_remote_get( 'https://api.line.me/v2/profile', array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 15,
            ));
            if ( is_wp_error($u) ) { $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_profile']; roro_auth_redirect_back(); }
            $profile = json_decode( wp_remote_retrieve_body($u), true );
            $name    = $profile['displayName'] ?? '';
            // メールは未取得の可能性があるため仮の一意IDでWPユーザー作成
            $username = 'line_' . strtolower( preg_replace('/\s+/', '_', $name ?: ('user' . wp_generate_password(6, false)) ) );
            $email    = $username . '@example.com';

            $user = get_user_by('login', $username);
            if ( !$user ) {
                $uid = wp_create_user( $username, wp_generate_password(), $email );
                if ( is_wp_error($uid) ) { $_SESSION['roro_auth_error'] = $uid->get_error_message(); roro_auth_redirect_back(); }
                roro_auth_link_user_to_customer($uid);
                $user = get_user_by('ID', $uid);
            }
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            wp_safe_redirect( home_url() );
            exit;
        }

        // state 不一致
        $_SESSION['roro_auth_error'] = $roro_auth_messages['error_oauth_state'] ?? 'OAuth state error.';
        roro_auth_redirect_back();
    }
}

/* ==========================================================
 * カスタムテーブル連携
 * ========================================================== */
function roro_auth_link_user_to_customer($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . RORO_AUTH_CUSTOMER_TABLE;

    // 既に紐付けがある場合は重複登録しない
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id
    ));
    if ( $exists ) { return; }

    $wpdb->insert($table, array(
        'user_id'    => $user_id,
        'created_at' => current_time('mysql'),
    ));
}

/* ==========================================================
 * 管理画面設定ページ
 * ========================================================== */
add_action('admin_menu', 'roro_auth_add_settings_page');
function roro_auth_add_settings_page() {
    add_options_page(
        __('RORO Auth設定', 'roro-auth'),
        __('RORO Auth設定', 'roro-auth'),
        'manage_options',
        'roro-auth-settings',
        'roro_auth_render_settings_page'
    );
}

function roro_auth_render_settings_page() {
    global $roro_auth_messages;

    if ( !current_user_can('manage_options') ) return;

    if ( isset($_POST['roro_auth_settings_submit']) ) {
        check_admin_referer('roro_auth_settings');
        update_option('roro_auth_google_client_id',     sanitize_text_field($_POST['google_client_id']     ?? ''));
        update_option('roro_auth_google_client_secret', sanitize_text_field($_POST['google_client_secret'] ?? ''));
        update_option('roro_auth_line_client_id',       sanitize_text_field($_POST['line_client_id']       ?? ''));
        update_option('roro_auth_line_client_secret',   sanitize_text_field($_POST['line_client_secret']   ?? ''));
        echo '<div class="updated"><p>' . esc_html($roro_auth_messages['success_settings_saved']) . '</p></div>';
    }

    $google_id     = get_option('roro_auth_google_client_id', '');
    $google_secret = get_option('roro_auth_google_client_secret', '');
    $line_id       = get_option('roro_auth_line_client_id', '');
    $line_secret   = get_option('roro_auth_line_client_secret', '');
    ?>
    <div class="wrap">
      <h1><?php echo esc_html($roro_auth_messages['settings_title']); ?></h1>

      <form method="post" action="">
        <?php wp_nonce_field('roro_auth_settings'); ?>

        <h2><?php echo esc_html($roro_auth_messages['section_google']); ?></h2>
        <table class="form-table">
          <tr>
            <th><label for="google_client_id"><?php echo esc_html($roro_auth_messages['google_client_id']); ?></label></th>
            <td><input id="google_client_id" type="text" class="regular-text" name="google_client_id" value="<?php echo esc_attr($google_id); ?>"></td>
          </tr>
          <tr>
            <th><label for="google_client_secret"><?php echo esc_html($roro_auth_messages['google_client_secret']); ?></label></th>
            <td><input id="google_client_secret" type="text" class="regular-text" name="google_client_secret" value="<?php echo esc_attr($google_secret); ?>"></td>
          </tr>
        </table>

        <h2><?php echo esc_html($roro_auth_messages['section_line']); ?></h2>
        <table class="form-table">
          <tr>
            <th><label for="line_client_id"><?php echo esc_html($roro_auth_messages['line_client_id']); ?></label></th>
            <td><input id="line_client_id" type="text" class="regular-text" name="line_client_id" value="<?php echo esc_attr($line_id); ?>"></td>
          </tr>
          <tr>
            <th><label for="line_client_secret"><?php echo esc_html($roro_auth_messages['line_client_secret']); ?></label></th>
            <td><input id="line_client_secret" type="text" class="regular-text" name="line_client_secret" value="<?php echo esc_attr($line_secret); ?>"></td>
          </tr>
        </table>

        <?php submit_button(); ?>
      </form>

      <p><strong><?php echo esc_html($roro_auth_messages['redirect_url_hint']); ?></strong><br>
        <code><?php echo esc_html( home_url('/') ); ?></code>
      </p>
    </div>
    <?php
}
