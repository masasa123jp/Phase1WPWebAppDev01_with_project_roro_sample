<?php
/**
 * 分析用エンドポイント。
 *
 * 過去30日間のガチャ利用状況および収益を集計し、以下のKPIを返します。
 *  - today_spins: 本日（0時以降）のガチャ実行回数
 *  - active_days: 過去30日間にガチャが実行された日数
 *  - unique_customers: 過去30日間にガチャを回したユニークなカスタマー数
 *  - revenue_30d: 過去30日間の売上合計（roro_revenue の amount 合計）
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Analytics_Endpoint {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * RESTルートを登録する。
     */
    public function register(): void {
        register_rest_route( 'roro/v1', '/analytics', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * KPIを計算しレスポンスとして返す。
     *
     * @param WP_REST_Request $req リクエストオブジェクト（未使用）
     * @return WP_REST_Response
     */
    public function handle( WP_REST_Request $req ) : WP_REST_Response {
        global $wpdb;
        $log_table     = $wpdb->prefix . 'roro_gacha_log';
        $revenue_table = $wpdb->prefix . 'roro_revenue';
        // ガチャ統計
        $stats = $wpdb->get_row(
            "SELECT
                SUM(created_at >= CURDATE()) AS today_spins,
                COUNT(DISTINCT DATE(created_at)) AS active_days,
                COUNT(DISTINCT customer_id) AS unique_customers
             FROM {$log_table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ARRAY_A
        );
        // 収益統計
        $revenue_sum = (float) $wpdb->get_var(
            "SELECT SUM(amount) FROM {$revenue_table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        return rest_ensure_response( [
            'today_spins'      => (int) ( $stats['today_spins'] ?? 0 ),
            'active_days'      => (int) ( $stats['active_days'] ?? 0 ),
            'unique_customers' => (int) ( $stats['unique_customers'] ?? 0 ),
            'revenue_30d'      => $revenue_sum,
        ] );
    }
}
