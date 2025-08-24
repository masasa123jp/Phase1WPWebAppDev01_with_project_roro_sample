<?php
/**
 * 管理画面機能
 * - 「設定 > RORO Auth」に設定ページを追加
 * - 「全ユーザーに2FAを強制」等の最小限の設定を保持
 * - roro-core のユーティリティが存在する場合は動作状況の簡易確認も表示
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Auth_Admin {

    /** @var string 設定ページのフック名（アセット条件読み込みに使用） */
    private $page_hook = '';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * 設定メニューを追加（設定 > RORO Auth）
     */
    public function add_menu() {
        $this->page_hook = add_options_page(
            'RORO Auth 設定',     // ページタイトル
            'RORO Auth',          // メニュータイトル
            'manage_options',     // 権限
            'roro-auth',          // スラッグ
            [$this, 'render']     // 表示コールバック
        );
    }

    /**
     * 設定ページのアセット読み込み（対象ページのみ）
     */
    public function enqueue_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }
        wp_enqueue_style(
            'roro-auth-admin-css',
            RORO_AUTH_URL . 'assets/css/roro-auth-admin.css',
            [],
            RORO_AUTH_VERSION
        );
        wp_enqueue_script(
            'roro-auth-admin-js',
            RORO_AUTH_URL . 'assets/js/roro-auth-admin.js',
            [],
            RORO_AUTH_VERSION,
            true
        );
    }

    /**
     * 設定ページ本体
     * - nonce 検証のうえオプション保存
     * - 現在の設定値をフォームへ反映
     * - roro-core の定数/クラスがあれば簡易ステータス表示
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 保存処理
        if (isset($_POST['roro_auth_settings_nonce']) &&
            wp_verify_nonce($_POST['roro_auth_settings_nonce'], 'roro_auth_save_settings')) {

            $force_all = isset($_POST['force_all']) ? 1 : 0;
            update_option('roro_auth_force_all', $force_all);

            echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
        }

        $force_all_enabled = (int) get_option('roro_auth_force_all', 0);
        ?>
        <div class="wrap settings_page_roro-auth">
            <h1>RORO Auth 設定</h1>

            <form method="post" action="">
                <?php wp_nonce_field('roro_auth_save_settings', 'roro_auth_settings_nonce'); ?>

                <h2>二要素認証（2FA / OTP）</h2>
                <p>
                    <label>
                        <input type="checkbox" name="force_all" value="1" <?php checked(1, $force_all_enabled); ?> />
                        全ユーザーに2FA（ログイン時のワンタイムコード入力）を要求する
                    </label>
                </p>

                <?php submit_button('設定を保存'); ?>
            </form>

            <hr />

            <h2>システムステータス</h2>
            <p>
                <?php if (defined('RORO_CORE_WP_DIR')): ?>
                    RORO Core 読み込み: <code><?php echo esc_html(RORO_CORE_WP_DIR); ?></code><br>
                <?php else: ?>
                    <strong style="color:#a00;">RORO Core の定数が見つかりません（未有効/未ロードの可能性）。</strong><br>
                <?php endif; ?>

                <?php
                // roro-core 側に RORO_Auth_Utils があれば、簡易的にテスト出力（存在チェック付き）
                if (class_exists('RORO_Auth_Utils')) {
                    if (method_exists('RORO_Auth_Utils', 'generate_code')) {
                        $sample = RORO_Auth_Utils::generate_code(6);
                        echo 'サンプルOTP（6桁）: <code>' . esc_html($sample) . '</code><br>';
                    } else {
                        echo 'RORO_Auth_Utils は存在しますが generate_code() が見つかりません。<br>';
                    }
                } else {
                    echo 'RORO_Auth_Utils クラスが見つかりません（コア側の提供がない構成です）。<br>';
                }
                ?>
            </p>
        </div>
        <?php
    }
}
