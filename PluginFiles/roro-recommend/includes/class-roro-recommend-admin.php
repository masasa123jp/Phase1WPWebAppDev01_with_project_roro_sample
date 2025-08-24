<?php
/**
 * RORO Recommend Admin - 管理画面（設定ページと初期データ管理）
 *
 * 変更点:
 * - $service->db の直接参照を廃止し、$service->get_db() に置き換え。
 * - Intelephense の Undefined property '$db'、PHP の private 可視性エラーを根本解消。
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Recommend_Admin {

    /**
     * 管理メニューに「RORO Recommend」ページを追加
     */
    public function register_menu() {
        add_menu_page(
            'RORO Recommend',            // ページタイトル
            'RORO Recommend',            // メニュータイトル
            'manage_options',            // 権限
            'roro-recommend',            // メニュースラッグ
            array($this, 'render_page'), // 表示コールバック
            'dashicons-thumbs-up',       // アイコン
            56                           // 表示位置
        );
    }

    /**
     * プラグインの管理ページを表示（統計情報と初期データ投入）
     */
    public function render_page() {
        if (!current_user_can('manage_options')) return;

        // サービス層を準備
        $service  = new RORO_Recommend_Service();
        $lang     = $service->detect_lang();
        $messages = $service->load_lang($lang);

        // 「初期データ投入」ボタン押下時: マスタが空の場合に投入
        if (isset($_POST['roro_seed']) && check_admin_referer('roro_recommend_seed')) {
            $service->maybe_seed();
            echo '<div class="updated notice is-dismissible"><p>' . esc_html($messages['seed_done']) . '</p></div>';
        }

        // ▼ ここが主な修正点：DB 参照は get_db() から取得して使用する
        $db      = $service->get_db();     // ← $service->db ではなくアクセサ経由で取得
        $tables  = $service->tables();

        // 統計情報（件数）を取得
        $advice_count = (int) $db->get_var("SELECT COUNT(*) FROM {$tables['advice']}");
        $spot_count   = (int) $db->get_var("SELECT COUNT(*) FROM {$tables['spot']}");
        $log_count    = (int) $db->get_var("SELECT COUNT(*) FROM {$tables['log']}");

        ?>
        <div class="wrap">
            <h1>RORO Recommend</h1>
            <p><?php echo esc_html($messages['admin_desc']); ?></p>

            <h2><?php echo esc_html($messages['admin_stats']); ?></h2>
            <ul>
                <li><?php echo esc_html($messages['stat_advice']); ?>: <?php echo $advice_count; ?></li>
                <li><?php echo esc_html($messages['stat_spot']); ?>: <?php echo $spot_count; ?></li>
                <li><?php echo esc_html($messages['stat_logs']); ?>: <?php echo $log_count; ?></li>
            </ul>

            <form method="post">
                <?php wp_nonce_field('roro_recommend_seed'); ?>
                <p>
                    <button type="submit" class="button button-primary" name="roro_seed" value="1">
                        <?php echo esc_html($messages['btn_seed']); ?>
                    </button>
                </p>
            </form>

            <p style="margin-top: 2em; color: #666;">
                <?php echo nl2br(esc_html($messages['admin_note'])); ?>
            </p>
        </div>
        <?php
    }
}
