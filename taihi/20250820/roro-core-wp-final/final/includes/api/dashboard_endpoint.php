<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/dashboard_endpoint.php
 *
 * 管理者向けダッシュボードKPIエンドポイント。
 * 30日間にガチャを回したユニークカスタマー数（active_30d）、本日の広告クリック率（ad_click_rate）、
 * 当月の総売上（revenue_current）を返します。管理者のみ利用可能です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use wpdb;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class Dashboard_Endpoint extends WP_REST_Controller {
    /** @var wpdb WordPress DB オブジェクト */
    private wpdb $db;

    public function __construct( wpdb $wpdb ) {
        $this->db        = $wpdb;
        $this->namespace = 'roro/v1';
        $this->rest_base = 'dashboard';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * ルートを登録します。
     */
    public function register_routes(): void {
        register_rest_route( $this->namespace, "/{$this->rest_base}", [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_kpi' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * KPIを計算して返却します。
     *
     * @param WP_REST_Request $req リクエスト
     * @return WP_REST_Response
     */
    public function get_kpi( WP_REST_Request $req ) : WP_REST_Response {
        $p = $this->db->prefix;
        // 過去30日間にガチャを回したユニークカスタマー数
        $active30 = (int) $this->db->get_var(
            "SELECT COUNT(DISTINCT customer_id)
               FROM {$p}roro_gacha_log
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        // 本日の広告クリック率
        $impressions = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$p}roro_gacha_log
              WHERE prize_type='ad' AND created_at >= CURDATE()"
        );
        $clicks = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$p}roro_ad_click
              WHERE clicked_at >= CURDATE()"
        );
        $ctr = 0.0;
        if ( $impressions > 0 ) {
            $ctr = round( $clicks / $impressions, 3 );
        }
        // 当月売上
        $revenueMo = (float) $this->db->get_var(
            "SELECT COALESCE(SUM(amount),0)
               FROM {$p}roro_revenue
              WHERE DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')"
        );
        return rest_ensure_response( [
            'active_30d'      => $active30,
            'ad_click_rate'   => $ctr,
            'revenue_current' => $revenueMo,
        ] );
    }
}
