<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/repeat_usage_endpoint.php
 *
 * リピート利用率エンドポイント。
 * 過去30日間で2回以上ガチャを実行した顧客を集計し、全体に対する割合を計算して返します。
 * 管理者専用です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Repeat_Usage_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/analytics/repeat-usage';

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
     * リピート率を計算する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $log_table = $wpdb->prefix . 'roro_gacha_log';
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id)
               FROM {$log_table}
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        if ( $total === 0 ) {
            return rest_ensure_response( [ 'repeat_percentage' => 0 ] );
        }
        $repeat = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT customer_id
                  FROM {$log_table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY customer_id
                HAVING COUNT(*) > 1
            ) tmp"
        );
        $percentage = round( ( $repeat / $total ) * 100, 2 );
        return rest_ensure_response( [ 'repeat_percentage' => $percentage ] );
    }
}
