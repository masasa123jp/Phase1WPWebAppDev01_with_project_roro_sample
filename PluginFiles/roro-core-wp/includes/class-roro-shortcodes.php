<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class RORO_Shortcodes {
    public static function init(): void {
        add_shortcode('roro_app', [self::class, 'render_app']);
    }

    public static function render_app(array $atts = []): string {
        // アセットはショートコード描画時に読み込み
        wp_enqueue_style('roro-core');
        wp_enqueue_script('roro-core-i18n');
        wp_enqueue_script('roro-core-app');

        ob_start();
        include RORO_CORE_WP_DIR . 'templates/app-index.php';
        return (string) ob_get_clean();
    }
}
