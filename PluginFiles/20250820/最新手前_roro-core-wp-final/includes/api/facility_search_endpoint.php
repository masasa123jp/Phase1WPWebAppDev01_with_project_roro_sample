<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/facility_search_endpoint.php
 *
 * 施設検索エンドポイント。
 * species (dog/cat/both)、category、位置情報 (lat, lng)、半径 (radius) をパラメータとして受け取り、条件に合致する施設を返します。
 * 緯度・経度が指定される場合は ST_Distance_Sphere() を使用して距離を算出し、指定半径内にある施設のみを返します。
 * レビュー平均を含めて返すため、roro_facility_review テーブルとJOINします。
 * 未知カテゴリの場合は施設検索をスキップしますが、拡張でイベントや教材検索を追加することを想定しています。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Facility_Search_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/facilities';

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
                'permission_callback' => '__return_true',
                'args'                => [
                    'species'  => [ 'type' => 'string', 'required' => true ],
                    'category' => [ 'type' => 'string', 'required' => true ],
                    'lat'      => [ 'type' => 'number', 'required' => false ],
                    'lng'      => [ 'type' => 'number', 'required' => false ],
                    'radius'   => [ 'type' => 'integer', 'required' => false, 'default' => 3000 ],
                    'limit'    => [ 'type' => 'integer', 'required' => false, 'default' => 20 ],
                ],
            ],
        ] );
    }

    /**
     * 施設検索処理を行う。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $species  = sanitize_text_field( $request->get_param( 'species' ) );
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $lat      = $request->get_param( 'lat' );
        $lng      = $request->get_param( 'lng' );
        $radius   = (int) $request->get_param( 'radius' );
        $limit    = (int) $request->get_param( 'limit' );
        // 許可カテゴリリスト
        $allowed_cat = [ 'cafe','hospital','salon','park','hotel','school','store' ];
        $rows        = [];
        if ( in_array( $category, $allowed_cat, true ) ) {
            if ( $lat !== null && $lng !== null ) {
                // 距離を計算して半径内の施設を返す
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT f.facility_id AS id, f.name, f.category, f.lat, f.lng,
                            ST_Distance_Sphere(Point(f.lng, f.lat), Point(%f,%f)) AS distance,
                            COALESCE(AVG(r.rating),0) AS avg_rating
                       FROM {$wpdb->prefix}roro_facility f
                  LEFT JOIN {$wpdb->prefix}roro_facility_review r ON f.facility_id = r.facility_id
                      WHERE f.category = %s
                   GROUP BY f.facility_id
                     HAVING distance <= %d
                   ORDER BY distance ASC
                   LIMIT %d",
                    $lng,
                    $lat,
                    $category,
                    $radius,
                    $limit
                ), ARRAY_A );
            } else {
                // 距離計算なしで返す
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT f.facility_id AS id, f.name, f.category, f.lat, f.lng,
                            0 AS distance,
                            COALESCE(AVG(r.rating),0) AS avg_rating
                       FROM {$wpdb->prefix}roro_facility f
                  LEFT JOIN {$wpdb->prefix}roro_facility_review r ON f.facility_id = r.facility_id
                      WHERE f.category = %s
                   GROUP BY f.facility_id
                   ORDER BY f.name
                   LIMIT %d",
                    $category,
                    $limit
                ), ARRAY_A );
            }
        }
        return rest_ensure_response( [ 'facilities' => $rows ] );
    }
}
