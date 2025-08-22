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
        // 画像ディレクトリのURLを取得。プラグイン本体で定義済みの定数を利用します。
        $assets_url = defined('RORO_CORE_WP_URL') ? trailingslashit(RORO_CORE_WP_URL . 'assets/images') : '';
        $logo_url   = $assets_url . 'logo_roro.png';
        ?>
        <div class="wrap roro-db-wrapper">
            <style>
                /* プラグイン専用スタイル：テーマのカラーパレットを引用して柔らかな印象に */
                .roro-db-wrapper {
                    background-color: #F9F9F9;
                    padding: 0;
                    margin: 0;
                }
                .roro-db-wrapper .roro-header {
                    text-align: center;
                    padding: 2rem 1rem;
                    background-color: #FFFFFF;
                    border-bottom: 4px solid #1F497D;
                }
                .roro-db-wrapper .roro-header img.roro-logo {
                    max-width: 200px;
                    width: 100%;
                    height: auto;
                    margin-bottom: 0.5rem;
                }
                .roro-db-wrapper .roro-header .roro-title {
                    font-size: 1.8rem;
                    color: #1F497D;
                    margin: 0;
                    font-weight: bold;
                }
                .roro-db-wrapper .roro-header .roro-tagline {
                    font-size: 1rem;
                    color: #333333;
                    margin-top: 0.25rem;
                }
                .roro-db-wrapper .roro-content {
                    padding: 1.5rem;
                }
                .roro-db-wrapper ol.roro-file-list {
                    padding-left: 1.5rem;
                    margin-top: 0.5rem;
                    margin-bottom: 1.5rem;
                }
                .roro-db-wrapper ol.roro-file-list li {
                    margin-bottom: 0.25rem;
                    list-style-type: decimal;
                }
                .roro-db-wrapper .roro-actions {
                    margin-top: 1rem;
                }
            </style>
            <!-- ヘッダーセクション -->
            <div class="roro-header">
                <?php if ($logo_url) : ?>
                    <img class="roro-logo" src="<?php echo esc_url($logo_url); ?>" alt="Project RORO Logo">
                <?php endif; ?>
                <div class="roro-title">Roro DB Importer</div>
                <div class="roro-tagline">セットアップ用データベースのインポートツール</div>
            </div>
            <!-- コンテンツセクション -->
            <div class="roro-content">
                <p>以下の SQL ファイルを順番に実行して、スキーマおよび初期データを投入します。</p>
                <h2>検出ファイル</h2>
                <ol class="roro-file-list">
                    <?php foreach ($files as $f) : ?>
                        <li><?php echo esc_html(basename($f)); ?></li>
                    <?php endforeach; ?>
                </ol>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('roro_db_import'); ?>
                    <input type="hidden" name="action" value="roro_db_import">
                    <p>
                        <label><input type="checkbox" name="use_tx" value="1" checked> トランザクションを使用して実行</label>
                    </p>
                    <p class="roro-actions">
                        <button class="button button-primary" type="submit">今すぐインポートを実行</button>
                    </p>
                </form>
                <p>ログは <code>wp-content/uploads/roro-core/logs/</code> に出力されます。</p>
            </div>
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