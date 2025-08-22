<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/flow_analysis_endpoint.php
 *
 * 行動フロー分析エンドポイント。
 * レポートを投稿した顧客数と、課題情報を含むレポートを投稿した顧客数をカウントし、ファネルデータを返します。
 * 管理者権限でのみ利用可能です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Flow_Analysis_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/analytics/flow';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
    }

    /**
     * ファネルデータを生成する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $report_table = $wpdb->prefix . 'roro_report';
        $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT customer_id) FROM {$report_table}" );
        $with_issues = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id)
               FROM {$report_table}
              WHERE JSON_LENGTH(content->'$.issues') > 0"
        );
        $steps = [
            [ 'step' => 'レポート提出', 'count' => $total ],
            [ 'step' => '課題ありレポート', 'count' => $with_issues ],
        ];
        return rest_ensure_response( $steps );
    }
}
