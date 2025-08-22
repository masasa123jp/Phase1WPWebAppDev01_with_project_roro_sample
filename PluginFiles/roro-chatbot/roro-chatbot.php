<?php
/**
 * Plugin Name: Roro Chatbot
 * Plugin URI: https://example.com/roro-chatbot
 * Description: ショートコード <code>[roro_chatbot]</code> でチャットボットを埋め込むための最小プラグインです。設定画面でウェルカムメッセージを変更できます。
 * Version: 1.6.0-rc2
 * Author: Project Roro
 * License: GPL-2.0-or-later
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: roro-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Roro_Chatbot_Plugin {
    const VERSION = '1.6.0-rc2';

    public function __construct() {
        add_shortcode( 'roro_chatbot', [ $this, 'render_chatbot' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function enqueue_assets() {
        wp_register_script( 'roro-chatbot', plugins_url( 'assets/chatbot.js', __FILE__ ), [], self::VERSION, true );
        wp_register_style( 'roro-chatbot', plugins_url( 'assets/chatbot.css', __FILE__ ), [], self::VERSION );
    }

    public function render_chatbot( $atts = [] ) {
        $atts = shortcode_atts( [
            'welcome' => get_option( 'roro_chatbot_welcome', 'こんにちは！ロロです。ご質問どうぞ。' ),
        ], $atts, 'roro_chatbot' );

        wp_enqueue_script( 'roro-chatbot' );
        wp_enqueue_style( 'roro-chatbot' );

        $welcome = esc_html( $atts['welcome'] );
        ob_start();
        ?>
        <div id="roro-chatbot" class="roro-chatbot" role="region" aria-live="polite">
            <div class="roro-chatbot__window">
                <p class="roro-chatbot__message"><?php echo $welcome; ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function admin_menu() {
        add_options_page(
            'Roro Chatbot',
            'Roro Chatbot',
            'manage_options',
            'roro-chatbot',
            [ $this, 'settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'roro_chatbot_settings', 'roro_chatbot_welcome', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'こんにちは！ロロです。ご質問どうぞ。',
        ] );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
          <h1>Roro Chatbot 設定</h1>
          <form method="post" action="options.php">
            <?php settings_fields( 'roro_chatbot_settings' ); ?>
            <?php do_settings_sections( 'roro_chatbot_settings' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="roro_chatbot_welcome">ウェルカムメッセージ</label></th>
                    <td>
                        <input type="text" id="roro_chatbot_welcome" name="roro_chatbot_welcome" value="<?php echo esc_attr( get_option( 'roro_chatbot_welcome' ) ); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
          </form>
          <p>チャットボットを表示するには、固定ページや投稿に <code>[roro_chatbot]</code> を追加してください。</p>
        </div>
        <?php
    }
}

new Roro_Chatbot_Plugin();
