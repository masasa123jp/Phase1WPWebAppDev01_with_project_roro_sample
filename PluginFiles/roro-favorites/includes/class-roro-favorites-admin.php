<?php
/**
 * 管理画面（統計/注意書き）
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Favorites_Admin {

    public function register_menu() {
        add_menu_page(
            'RORO Favorites',
            'RORO Favorites',
            'manage_options',
            'roro-favorites',
            [ $this, 'render_page' ],
            'dashicons-heart',
            57
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) return;
        $svc = new RORO_Favorites_Service();
        $lang = $svc->detect_lang();
        $M = $svc->load_lang($lang);
        $t = $svc->tables();
        global $wpdb;
        // お気に入り総数と種類別件数を取得
        $total = intval($wpdb->get_var("SELECT COUNT(*) FROM {$t['fav']}"));
        $spots = intval($wpdb->get_var("SELECT COUNT(*) FROM {$t['fav']} WHERE target_type='spot'"));
        $events = intval($wpdb->get_var("SELECT COUNT(*) FROM {$t['fav']} WHERE target_type='event'"));
        ?>
        <div class="wrap">
            <h1>RORO Favorites</h1>
            <p><?php echo esc_html($M['admin_desc']); ?></p>
            <h2><?php echo esc_html($M['admin_stats']); ?></h2>
            <ul>
                <li><?php echo esc_html($M['stat_total']); ?>: <?php echo $total; ?></li>
                <li><?php echo esc_html($M['stat_spot']); ?>: <?php echo $spots; ?></li>
                <li><?php echo esc_html($M['stat_event']); ?>: <?php echo $events; ?></li>
            </ul>
            <p style="margin-top:1em; color:#666;"><?php echo esc_html($M['admin_note']); ?></p>
        </div>
        <?php
    }
}
