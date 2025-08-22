<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理画面に「Roro DB Importer」を追加し、手動実行できるようにするクラス。
 */
class Roro_Admin {
    /**
     * 初期化: メニュー登録と処理フックを追加
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_roro_db_import', [__CLASS__, 'handle_import']);
    }

    /**
     * 管理画面メニューに「Tools > Roro DB Importer」を追加
     */
    public static function menu() {
        add_management_page(
            'Roro DB Importer',
            'Roro DB Importer',
            'manage_options',
            'roro-db-importer',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * ページ描画: SQL ファイル一覧と実行ボタン
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $files = Roro_DB::list_sql_files();
        ?>
        <div class="wrap">
            <h1>Roro DB Importer</h1>
            <p>assets/sql 配下の .sql を上から順に実行します（推奨順に再整列済み）。</p>
            <h2>検出ファイル</h2>
            <ol>
                <?php foreach ($files as $f) : ?>
                    <li><?php echo esc_html(basename($f)); ?></li>
                <?php endforeach; ?>
            </ol>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('roro_db_import'); ?>
                <input type="hidden" name="action" value="roro_db_import">
                <p>
                    <label><input type="checkbox" name="use_tx" value="1" checked> トランザクションで実行</label>
                </p>
                <p>
                    <button class="button button-primary" type="submit">今すぐインポートを実行</button>
                </p>
            </form>
            <p>ログは <code>wp-content/uploads/roro-core/logs/</code> に出力します。</p>
        </div>
        <?php
    }

    /**
     * フォーム送信時にSQLファイルの実行を行う
     */
    public static function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('roro_db_import');
        $use_tx = !empty($_POST['use_tx']);
        if (!is_dir(RORO_DB_LOG_DIR)) {
            wp_mkdir_p(RORO_DB_LOG_DIR);
        }
        $log = Roro_DB::make_logger();
        $order = [
            'ER_20250815.sql',
            'initial_data_with_latlng_fixed_BASIC.sql',
            'initial_data_with_latlng_fixed_GMAP.sql',
            'initial_data_with_latlng_fixed_OPAM.sql',
            'initial_data_with_latlng_fixed_TSM.sql',
            'initial_data_with_latlng_fixed_CDLM.sql',
            'initial_data_with_latlng_fixed_EVENT_MASTER.sql',
        ];
        try {
            Roro_DB::import_files_in_order($order, $log, $use_tx);
            $log->info('Manual import finished successfully.');
            wp_safe_redirect(add_query_arg(['page' => 'roro-db-importer', 'roro-status' => 'ok'], admin_url('tools.php')));
        } catch (Exception $e) {
            $log->error('Manual import failed: ' . $e->getMessage());
            wp_safe_redirect(add_query_arg(['page' => 'roro-db-importer', 'roro-status' => 'ng'], admin_url('tools.php')));
        }
        exit;
    }
}