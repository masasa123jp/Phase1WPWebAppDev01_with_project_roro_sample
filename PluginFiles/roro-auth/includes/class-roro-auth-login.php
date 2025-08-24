<?php
/**
 * ログイン拡張機能
 * - WPログインフォームに OTP 入力欄を追加
 * - authenticate フィルタで OTP 検証を追加
 * - グローバル強制（force_all オプション）または roro-core の per-user 設定を尊重
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Auth_Login {

    public function __construct() {
        // ログインフォームに OTP 入力欄を追加
        add_action('login_form', [$this, 'render_otp_field']);

        // ログイン画面用のCSS/JSを読み込み
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);

        // 認証フローの末尾で OTP 検証を実施（ユーザー名/パスワードが正しい場合に続けて検証）
        add_filter('authenticate', [$this, 'verify_otp'], 100, 3);
    }

    /**
     * ログインフォームへ OTP 入力欄を出力
     * - 2FA対象ユーザー以外でも表示されます（未入力可）
     */
    public function render_otp_field() {
        ?>
        <p id="roro-auth-otp-field">
            <label for="roro_auth_code">
                ワンタイムパスコード（OTP）<br>
                <input type="text" name="roro_auth_code" id="roro_auth_code" class="input" size="20" autocomplete="off" />
            </label>
        </p>
        <?php
    }

    /**
     * ログイン画面用アセット
     */
    public function enqueue_login_assets() {
        wp_enqueue_style(
            'roro-auth-login-css',
            RORO_AUTH_URL . 'assets/css/roro-auth.css',
            [],
            RORO_AUTH_VERSION
        );
        wp_enqueue_script(
            'roro-auth-login-js',
            RORO_AUTH_URL . 'assets/js/roro-auth-login.js',
            [],
            RORO_AUTH_VERSION,
            true
        );
    }

    /**
     * OTP 検証
     * - $user が WP_User であれば（ID/パスが正しい）追加検証へ
     * - 2FA 要求ユーザー（全体強制 or 個別有効）に対して OTP を検証
     * - roro-core のユーティリティ（is_twofactor_enabled / verify_code）があれば利用、なければグローバル設定のみで判定
     *
     * @param WP_User|WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return WP_User|WP_Error
     */
    public function verify_otp($user, $username, $password) {
        // 既に前段でエラーなら何もしない
        if (is_wp_error($user) || !$user) {
            return $user;
        }

        $user_id = (int) $user->ID;

        // 2FA の要否判定（グローバル強制 or 個別設定）
        $requires_otp = (bool) get_option('roro_auth_force_all', 0);

        // roro-core のユーティリティがあれば個別設定を尊重
        if (class_exists('RORO_Auth_Utils') && method_exists('RORO_Auth_Utils', 'is_twofactor_enabled')) {
            $requires_otp = $requires_otp || RORO_Auth_Utils::is_twofactor_enabled($user_id);
        }

        if (!$requires_otp) {
            // 要求なし（OTP未入力でも通す）
            return $user;
        }

        // OTP 入力値
        $otp_code = isset($_POST['roro_auth_code']) ? sanitize_text_field($_POST['roro_auth_code']) : '';

        if ($otp_code === '') {
            // 未入力はエラー
            return new WP_Error(
                'roro_auth_no_code',
                '<strong>エラー:</strong> ワンタイムパスコードが入力されていません。'
            );
        }

        // 検証（roro-core 側の verify_code があれば使用）
        $valid = false;
        if (class_exists('RORO_Auth_Utils') && method_exists('RORO_Auth_Utils', 'verify_code')) {
            $valid = (bool) RORO_Auth_Utils::verify_code($user_id, $otp_code);
        } else {
            // コアが提供しない場合は検証不能なため無効扱い
            $valid = false;
        }

        if (!$valid) {
            return new WP_Error(
                'roro_auth_invalid_code',
                '<strong>エラー:</strong> ワンタイムパスコードが正しくありません。'
            );
        }

        // ここまで来れば OTP 検証OK
        return $user;
    }
}
