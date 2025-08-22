<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/admin/dashboard_widget.php
 *
 * RoRo 管理ダッシュボードのウィジェットを定義します。WordPress ダッシュボードに
 * 「RoRo KPI」ウィジェットを追加し、REST API 経由で取得した分析データを表示します。
 * このウィジェットは旧 `admin/class-dashboard-widget.php` および
 * `includes/admin/class-endpoint-widget.php` の機能を統合しており、
 * 今日のガチャ回数、アクティブ日数、ユニークIP数、月間アクティブユーザー数などの
 * 指標を一括表示します。データは `roro/v1/analytics` エンドポイントから取得します。
 *
 * @package RoroCore\Admin
 */

declare( strict_types = 1 );

namespace RoroCore\Admin;

use function wp_remote_get;
use function wp_remote_retrieve_body;

/**
 * RoRo KPI ダッシュボードウィジェットクラス。
 *
 * このクラスは静的 init() メソッドを提供しており、wp_dashboard_setup アクションを
 * 通じてウィジェット登録を行います。表示には REST API を利用し、取得した JSON
 * データをエスケープして出力します。
 */
class Dashboard_Widget {

    /**
     * ウィジェット登録を初期化します。
     */
    public static function init(): void {
        add_action( 'wp_dashboard_setup', [ self::class, 'register' ] );
    }

    /**
     * WordPress にウィジェットを登録します。
     */
    public static function register(): void {
        wp_add_dashboard_widget(
            'roro_kpi_widget',
            __( 'RoRo KPI', 'roro-core' ),
            [ self::class, 'render' ]
        );
    }

    /**
     * ウィジェットを描画します。REST API から統計情報を取得し、リストとして表示します。
     * サーバーエラーが発生した場合はエラーメッセージを表示します。
     */
    public static function render(): void {
        // REST API にアクセスして統計データを取得
        $response = wp_remote_get( home_url( '/wp-json/roro/v1/analytics' ) );
        if ( is_wp_error( $response ) ) {
            echo '<p>' . esc_html__( 'Failed to fetch analytics data.', 'roro-core' ) . '</p>';
            return;
        }
        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        // データが配列でない場合は空配列として扱う
        if ( ! is_array( $data ) ) {
            $data = [];
        }
        echo '<ul style="margin:0;padding-left:1.2em">';
        // Today Spins (本日のガチャ回数)
        echo '<li>' . esc_html__( 'Today Spins', 'roro-core' ) . ': ' . esc_html( $data['today_spins'] ?? 'N/A' ) . '</li>';
        // Active Days (アクティブ日数)
        echo '<li>' . esc_html__( 'Active Days', 'roro-core' ) . ': ' . esc_html( $data['active_days'] ?? 'N/A' ) . '</li>';
        // Unique IPs 30d (30日間のユニークIP数)
        echo '<li>' . esc_html__( 'Unique IPs 30d', 'roro-core' ) . ': ' . esc_html( $data['unique_ips_30d'] ?? 'N/A' ) . '</li>';
        // Monthly Active Users (月間アクティブユーザー)
        echo '<li>' . esc_html__( 'Monthly Active Users', 'roro-core' ) . ': ' . esc_html( $data['mau'] ?? 'N/A' ) . '</li>';
        echo '</ul>';
    }
}

// 即時初期化。これによりプラグイン読み込み時にウィジェットが登録されます。
Dashboard_Widget::init();
