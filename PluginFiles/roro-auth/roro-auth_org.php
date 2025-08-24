<?php
/**
 * Plugin Name: RORO Auth (Google & LINE Social Login)
 * Description: Google/LINEのOAuth2によるソーシャルログインを提供。多言語UI、管理画面設定、CSRF(state)対策、WPユーザー作成/照合、（任意）RORO_CUSTOMER連携に対応。
 * Version: 1.6.0
 * Author: Project RORO
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('RORO_AUTH_VERSION', '1.6.0');
define('RORO_AUTH_PLUGIN_FILE', __FILE__);
define('RORO_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RORO_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RORO_AUTH_OPTION_KEY', 'roro_auth_settings');

require_once RORO_AUTH_PLUGIN_DIR . 'includes/class-roro-auth-utils.php';
require_once RORO_AUTH_PLUGIN_DIR . 'includes/class-roro-auth-admin.php';
require_once RORO_AUTH_PLUGIN_DIR . 'includes/class-roro-auth-provider-google.php';
require_once RORO_AUTH_PLUGIN_DIR . 'includes/class-roro-auth-provider-line.php';

class RORO_Auth_Plugin {
    private static $instance = null;
    private $settings;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // 設定読み込み（存在しない場合はデフォルト）
        $this->settings = RORO_Auth_Utils::get_settings();

        // 言語メッセージ読み込み
        RORO_Auth_Utils::load_messages();

        // 有効化フック
        register_activation_hook(RORO_AUTH_PLUGIN_FILE, [$this, 'on_activate']);

        // フロント資産
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // 管理画面
        add_action('admin_menu', ['RORO_Auth_Admin', 'register_menu']);
        add_action('admin_init', ['RORO_Auth_Admin', 'register_settings']);

        // ルーター（?roro_auth=login|callback）
        add_action('init', [$this, 'router']);

        // ショートコード
        add_shortcode('roro_social_login', [$this, 'shortcode_social_login']);
        add_shortcode('roro_login',        [$this, 'shortcode_login']);
        add_shortcode('roro_signup',       [$this, 'shortcode_signup']);
    }

    public function on_activate() {
        // デフォルト設定の初期化
        $defaults = RORO_Auth_Utils::default_settings();
        $saved = get_option(RORO_AUTH_OPTION_KEY);
        if (!is_array($saved)) {
            update_option(RORO_AUTH_OPTION_KEY, $defaults, false);
        } else {
            update_option(RORO_AUTH_OPTION_KEY, array_merge($defaults, $saved), false);
        }
    }

    public function enqueue_assets() {
        wp_register_style('roro-auth', RORO_AUTH_PLUGIN_URL . 'assets/css/roro-auth.css', [], RORO_AUTH_VERSION);
        wp_register_script('roro-auth', RORO_AUTH_PLUGIN_URL . 'assets/js/roro-auth.js', ['jquery'], RORO_AUTH_VERSION, true);
        wp_enqueue_style('roro-auth');
        wp_enqueue_script('roro-auth');
        wp_localize_script('roro-auth', 'RORO_AUTH_LOC', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'i18n'      => RORO_Auth_Utils::messages_js(),
        ]);
    }

    /**
     * ルーター: /?roro_auth=login&provider=google|line
     *         : /?roro_auth=callback&provider=google|line&code=...&state=...
     */
    public function router() {
        if (!isset($_GET['roro_auth'])) return;
        $action   = sanitize_key($_GET['roro_auth']);
        $provider = isset($_GET['provider']) ? sanitize_key($_GET['provider']) : '';

        if (!in_array($provider, ['google', 'line'], true)) {
            // 未対応プロバイダ（Apple/Facebook）→404相当
            status_header(400);
            wp_die(esc_html(RORO_Auth_Utils::t('provider_not_supported')));
        }

        $settings = RORO_Auth_Utils::get_settings();
        $enabled  = $settings['enabled_providers'];
        if (empty($enabled[$provider])) {
            status_header(403);
            wp_die(esc_html(RORO_Auth_Utils::t('provider_disabled')));
        }

        if ($action === 'login') {
            $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/');
            $state = RORO_Auth_Utils::generate_state($provider, $redirect_to);
            if ($provider === 'google') {
                $url = RORO_Auth_Provider_Google::authorize_url($state);
            } else {
                $url = RORO_Auth_Provider_LINE::authorize_url($state);
            }
            wp_redirect($url);
            exit;
        }

        if ($action === 'callback') {
            $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
            $code  = isset($_GET['code'])  ? sanitize_text_field($_GET['code'])  : '';
            $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

            if (!empty($error)) {
                // ユーザーがキャンセル等
                $msg = sprintf('%s: %s', RORO_Auth_Utils::t('oauth_error_received'), $error);
                RORO_Auth_Utils::flash('error', $msg);
                wp_redirect(home_url('/'));
                exit;
            }

            // state 検証 & redirect_to 取得
            $state_data = RORO_Auth_Utils::consume_state($provider, $state);
            if (!$state_data) {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('oauth_state_mismatch'));
                wp_redirect(home_url('/'));
                exit;
            }
            $redirect_to = $state_data['redirect_to'];

            if (empty($code)) {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('oauth_missing_code'));
                wp_redirect($redirect_to);
                exit;
            }

            // トークン交換 & ユーザ情報取得
            $profile = null;
            $error_msg = '';
            if ($provider === 'google') {
                $result = RORO_Auth_Provider_Google::exchange_and_profile($code);
            } else {
                $result = RORO_Auth_Provider_LINE::exchange_and_profile($code);
            }

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
            } else {
                $profile = $result; // ['sub','email','email_verified','name','picture','locale']
            }

            if (!$profile) {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('oauth_error_generic') . ($error_msg ? ' - ' . $error_msg : ''));
                wp_redirect($redirect_to);
                exit;
            }

            // WPユーザー作成/照合
            $user_id = $this->login_or_create_user($provider, $profile);
            if (is_wp_error($user_id)) {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('login_failed') . ' - ' . $user_id->get_error_message());
                wp_redirect($redirect_to);
                exit;
            }

            // ログイン処理
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', get_user_by('id', $user_id)->user_login, get_user_by('id', $user_id));

            RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('login_success'));
            wp_redirect($redirect_to);
            exit;
        }
    }

    /**
     * プロバイダプロフィールからWPユーザーを照合/作成し、（任意）RORO_CUSTOMERとリンク
     * @param string $provider 'google'|'line'
     * @param array  $profile ['sub','email','email_verified','name','picture','locale']
     * @return int|WP_Error
     */
    private function login_or_create_user($provider, $profile) {
        $sub     = $profile['sub'] ?? '';
        $email   = $profile['email'] ?? '';
        $name    = $profile['name'] ?? '';
        $picture = $profile['picture'] ?? '';
        $locale  = $profile['locale'] ?? '';

        if (!$sub) return new WP_Error('missing_sub', 'Missing provider sub');

        // 1) 既存ユーザーの特定（プロバイダIDメタ → Email）
        $meta_key = $provider === 'google' ? 'roro_auth_google_sub' : 'roro_auth_line_sub';
        $by_sub = get_users([
            'meta_key'   => $meta_key,
            'meta_value' => $sub,
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        if (!empty($by_sub)) {
            $user_id = (int)$by_sub[0];
            // プロフィール情報更新（任意）
            if ($picture) update_user_meta($user_id, 'roro_auth_avatar', esc_url_raw($picture));
            if ($locale)  update_user_meta($user_id, 'roro_auth_locale', sanitize_text_field($locale));
            // RORO_CUSTOMERリンクも念のため確保
            RORO_Auth_Utils::ensure_customer_link($user_id, $email, $name);
            return $user_id;
        }

        // 2) Email照合
        $user = $email ? get_user_by('email', $email) : false;
        if ($user) {
            $user_id = (int)$user->ID;
        } else {
            // 3) 新規作成（LINEでemail未許諾の場合はプレースホルダ）
            if (!$email) {
                $email = $provider . '_' . sanitize_user($sub, true) . '@example.local';
            }
            $base_login = $email ? sanitize_user(current(explode('@', $email)), true) : $provider . '_' . substr(md5($sub), 0, 8);
            $login = $base_login;
            $i = 1;
            while (username_exists($login)) {
                $login = $base_login . '_' . $i;
                $i++;
            }
            $random_pass = wp_generate_password(24, true, true);
            $user_id = wp_create_user($login, $random_pass, $email);
            if (is_wp_error($user_id)) return $user_id;

            // 表示名
            if ($name) {
                wp_update_user(['ID' => $user_id, 'display_name' => $name, 'nickname' => $name]);
            }
        }

        // 4) プロバイダIDメタ紐付け・アバター・ロケール
        update_user_meta($user_id, $meta_key, $sub);
        if ($picture) update_user_meta($user_id, 'roro_auth_avatar', esc_url_raw($picture));
        if ($locale)  update_user_meta($user_id, 'roro_auth_locale', sanitize_text_field($locale));
        update_user_meta($user_id, 'roro_auth_provider', $provider);

        // 5) （任意）RORO_CUSTOMER / RORO_USER_LINK_WP 連携
        RORO_Auth_Utils::ensure_customer_link($user_id, $email, $name);

        return $user_id;
    }

    /** ショートコード: ソーシャルログインボタン群 */
    public function shortcode_social_login($atts = []) {
        $atts = shortcode_atts([
            'redirect_to' => home_url('/'),
            'show_wp'     => 'no', // yes の場合は通常のWPログインリンクも表示
            'show_apple'  => 'no',
            'show_fb'     => 'no',
        ], $atts);
        ob_start();
        $messages = RORO_Auth_Utils::messages();
        $settings = RORO_Auth_Utils::get_settings();
        $enabled  = $settings['enabled_providers'];

        $template = RORO_AUTH_PLUGIN_DIR . 'templates/social-login.php';
        include $template;
        return ob_get_clean();
    }

    /** ショートコード: 通常ログインフォーム（最低限・WP標準へ委譲） */
    public function shortcode_login($atts = []) {
        ob_start();
        $template = RORO_AUTH_PLUGIN_DIR . 'templates/login-form.php';
        include $template;
        return ob_get_clean();
    }

    /** ショートコード: シンプル新規登録（WPユーザー作成） */
    public function shortcode_signup($atts = []) {
        $msg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_roro_signup_nonce']) && wp_verify_nonce($_POST['_roro_signup_nonce'], 'roro_signup')) {
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            $pass  = isset($_POST['password']) ? $_POST['password'] : '';
            $name  = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
            if (!$email || !is_email($email)) {
                $msg = '<div class="roro-auth-error">' . esc_html(RORO_Auth_Utils::t('error_invalid_email')) . '</div>';
            } elseif (email_exists($email)) {
                $msg = '<div class="roro-auth-error">' . esc_html(RORO_Auth_Utils::t('error_email_exists')) . '</div>';
            } elseif (!$pass || strlen($pass) < 8) {
                $msg = '<div class="roro-auth-error">' . esc_html(RORO_Auth_Utils::t('error_password_policy')) . '</div>';
            } else {
                $login = sanitize_user(current(explode('@', $email)), true);
                $i = 1;
                while (username_exists($login)) { $login = $login . '_' . $i; $i++; }
                $user_id = wp_create_user($login, $pass, $email);
                if (!is_wp_error($user_id)) {
                    if ($name) wp_update_user(['ID' => $user_id, 'display_name' => $name, 'nickname' => $name]);
                    // 直後ログイン
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id, true);
                    do_action('wp_login', get_user_by('id', $user_id)->user_login, get_user_by('id', $user_id));
                    // RORO_CUSTOMER連携（任意）
                    RORO_Auth_Utils::ensure_customer_link($user_id, $email, $name);
                    $msg = '<div class="roro-auth-success">' . esc_html(RORO_Auth_Utils::t('signup_success')) . '</div>';
                } else {
                    $msg = '<div class="roro-auth-error">' . esc_html(RORO_Auth_Utils::t('signup_failed')) . ': ' . esc_html($user_id->get_error_message()) . '</div>';
                }
            }
        }
        ob_start();
        $messages = RORO_Auth_Utils::messages();
        $messages_html = $msg;
        $template = RORO_AUTH_PLUGIN_DIR . 'templates/signup-form.php';
        include $template;
        return ob_get_clean();
    }
}

RORO_Auth_Plugin::instance();
