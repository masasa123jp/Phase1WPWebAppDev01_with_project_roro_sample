<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/ad_access_analysis_endpoint.php
 *
 * 広告アクセス分析エンドポイント。
 * 直近30日間のスポンサーごとに、広告インプレッション（ガチャで広告賞が当選した回数）とクリック数を集計します。
 * スポンサーごとのレポートを配列で返します。管理者のみ利用できます。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Ad_Access_Analysis_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/analytics/ad-access';

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
     * 広告アクセス分析を行う。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $p = $wpdb->prefix;
        $sponsor_table = $p . 'roro_sponsor';
        $gacha_table   = $p . 'roro_gacha_log';
        $click_table   = $p . 'roro_ad_click';
        $sponsors = $wpdb->get_results( "SELECT sponsor_id, name FROM {$sponsor_table} WHERE status = 'active'", ARRAY_A );
        $results  = [];
        foreach ( $sponsors as $sponsor ) {
            $sid         = (int) $sponsor['sponsor_id'];
            $impressions = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*)
                   FROM {$gacha_table}
                  WHERE prize_type = 'ad'
                    AND sponsor_id = %d
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $sid
            ) );
            $clicks = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*)
                   FROM {$click_table} c
                   JOIN {$p}roro_ad a ON c.ad_id = a.ad_id
                  WHERE a.sponsor_id = %d
                    AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $sid
            ) );
            $results[] = [
                'sponsor'     => $sponsor['name'],
                'impressions' => $impressions,
                'clicks'      => $clicks,
            ];
        }
        return rest_ensure_response( $results );
    }
}
