<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグイン有効化時にDBセットアップを実行するクラス。
 */
class Roro_Activator {
    /**
     * プラグイン有効化フック。SQLファイルを順番にインポートします。
     */
    public static function activate() {
        // ログディレクトリを確保
        if (!is_dir(RORO_DB_LOG_DIR)) {
            wp_mkdir_p(RORO_DB_LOG_DIR);
        }
        $log = Roro_DB::make_logger();

        // 推奨順序を定義（存在しない場合は無視）
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
            // トランザクション使用: true
            Roro_DB::import_files_in_order($order, $log, true);
            $log->info('Activation import finished successfully.');
        } catch (Exception $e) {
            // エラーを記録するが有効化は継続
            $log->error('Activation import failed: ' . $e->getMessage());
        }
    }
}