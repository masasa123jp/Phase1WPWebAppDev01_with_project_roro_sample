<?php
// wp-content/plugins/roro-core/class-cli-report.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class Roro_CLI_Report {
    public static function export() {
        // ... (CSV エクスポート処理は前回と同様)
    }
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'roro report', function() {
        Roro_CLI_Report::export();
        \WP_CLI::success( __( 'Export completed.', 'roro-core' ) );
    } );
}
