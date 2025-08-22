<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/admin/menu.php
 *
 * WordPressの管理画面にRoRo Core専用のトップレベルメニューとサブメニューを追加します。
 * ダッシュボードでは簡単な案内を表示し、設定ページはGeneral_Settingsのrender_page()を呼び出します。
 *
 * @package RoroCore\Admin
 */

namespace RoroCore\Admin;

class Menu {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    /**
     * メニュー登録。
     */
    public function register_menu() : void {
        add_menu_page(
            __( 'RoRo Dashboard', 'roro-core' ),
            __( 'RoRo',           'roro-core' ),
            'manage_options',
            'roro-core',
            [ $this, 'render_dashboard' ],
            'dashicons-pets',
            25
        );
        add_submenu_page(
            'roro-core',
            __( 'Settings', 'roro-core' ),
            __( 'Settings', 'roro-core' ),
            'manage_options',
            'roro-core-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * ダッシュボード表示。
     */
    public function render_dashboard() : void {
        echo '<div class="wrap"><h1>' . esc_html__( 'RoRo Dashboard', 'roro-core' ) . '</h1>';
        echo '<p>' . esc_html__( 'Welcome to the RoRo platform dashboard.', 'roro-core' ) . '</p>';
        echo '</div>';
    }

    /**
     * 設定画面表示。
     */
    public function render_settings() : void {
        ( new \RoroCore\Settings\General_Settings() )->render_page();
    }
}
