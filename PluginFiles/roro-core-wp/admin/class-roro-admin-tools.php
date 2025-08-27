<?php
/**
 * RORO Admin Tools
 * 管理画面にツールを追加し、簡易メンテナンス機能を提供。
 */

declare(strict_types=1);
defined('ABSPATH') || exit();

if (class_exists('RORO_Admin_Tools', false)) return;

final class RORO_Admin_Tools {

    // ユニークなメニュースラッグ。「-page」を付けるとフロント側 URL に書き換えられる場合があるため避ける。
    private const PAGE_SLUG    = 'roro-core-tools';
    private const NONCE_ACTION = 'roro_tools_action';
    private const NONCE_NAME   = '_roro_tools_nonce';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_roro_tools_action', [self::class, 'handle_post']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'roro-core-wp',
            __('RORO Tools', 'roro-core-wp'),
            __('Tools', 'roro-core-wp'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Insufficient permissions.', 'roro-core-wp'));

        $updated = isset($_GET['updated']) ? sanitize_key((string) $_GET['updated']) : '';
        $php_ver  = PHP_VERSION;
        $wp_ver   = get_bloginfo('version', 'display');
        $plug_ver = defined('RORO_CORE_WP_VER') ? RORO_CORE_WP_VER : 'n/a';
        $opt_key  = class_exists('RORO_Admin_Settings') ? RORO_Admin_Settings::OPTION : 'roro_core_settings';
        $opt_val  = get_option($opt_key, []);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('RORO Tools', 'roro-core-wp'); ?></h1>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(ucfirst($updated)) . ' ' . esc_html__('completed.', 'roro-core-wp'); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Environment', 'roro-core-wp'); ?></h2>
            <table class="widefat striped" style="max-width:780px">
                <tbody>
                <tr><th><?php echo esc_html__('PHP Version', 'roro-core-wp'); ?></th><td><?php echo esc_html($php_ver); ?></td></tr>
                <tr><th><?php echo esc_html__('WordPress Version', 'roro-core-wp'); ?></th><td><?php echo esc_html($wp_ver); ?></td></tr>
                <tr><th><?php echo esc_html__('Plugin Version', 'roro-core-wp'); ?></th><td><?php echo esc_html($plug_ver); ?></td></tr>
                <tr><th><?php echo esc_html__('Options Key', 'roro-core-wp'); ?></th><td><?php echo esc_html($opt_key); ?></td></tr>
                </tbody>
            </table>

            <h2 style="margin-top:24px;"><?php echo esc_html__('Maintenance', 'roro-core-wp'); ?></h2>
            <p><?php echo esc_html__('Run simple maintenance actions.', 'roro-core-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="action" value="roro_tools_action">
                <p><button class="button button-primary" name="op" value="flush_rewrite"><?php echo esc_html__('Flush rewrite rules', 'roro-core-wp'); ?></button></p>
            </form>
        </div>
        <?php
    }

    public static function handle_post(): void {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Insufficient permissions.', 'roro-core-wp'));
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            wp_die(esc_html__('Invalid request.', 'roro-core-wp'));
        }

        $op = sanitize_key((string)($_POST['op'] ?? ''));
        switch ($op) {
            case 'flush_rewrite':
                flush_rewrite_rules(false);
                wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=flush'));
                exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }
}
