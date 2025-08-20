<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/most_used_place_endpoint.php
 *
 * 最も利用された施設エンドポイント。
 * 過去30日間にガチャ当選した施設の回数を集計し、利用頻度上位5件を返します。
 * 管理者のみアクセス可能です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Most_Used_Place_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/analytics/most-used-places';

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
     * 上位施設を取得する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $log_table = $wpdb->prefix . 'roro_gacha_log';
        $fac_table = $wpdb->prefix . 'roro_facility';
        $rows = $wpdb->get_results(
            "SELECT f.name, COUNT(*) AS visits
               FROM {$log_table} g
               JOIN {$fac_table} f ON g.facility_id = f.facility_id
              WHERE g.prize_type = 'facility'
                AND g.created_at >= DATE_SUB( NOW(), INTERVAL 30 DAY )
           GROUP BY g.facility_id
           ORDER BY visits DESC, f.name
           LIMIT 5",
            ARRAY_A
        );
        return rest_ensure_response( $rows ?: [] );
    }
}
