<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/chart_data_endpoint.php
 *
 * Chart.js 用のデータを提供するエンドポイント。フロントエンドや管理画面でグラフ
 * 表示する際のデータソースとして利用します。デフォルトでは過去 12 か月の
 * 月間アクティブユーザー数を返しますが、将来的にはリクエストパラメータに応じて
 * 異なるデータセットを返すよう拡張できます。
 *
 * @package RoroCore\Api
 */

declare( strict_types = 1 );

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Chart.js 用データ提供エンドポイント。
 */
class Chart_Data_Endpoint {
    /** @var string RESTルート */
    public const ROUTE = '/chart-data';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルートを登録します。
     */
    public function register(): void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_data' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'type' => [ 'type' => 'string', 'required' => false, 'default' => 'mau' ],
            ],
        ] );
    }

    /**
     * データ取得処理。type パラメータによりデータセットを切り替えます。
     * 現在のサポート:
     *   - mau: 月間アクティブユーザー数
     *   - revenue: 月毎の収益
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public function get_data( WP_REST_Request $req ) {
        $type = $req->get_param( 'type' );
        switch ( $type ) {
            case 'revenue':
                return rest_ensure_response( $this->get_revenue_data() );
            case 'mau':
            default:
                return rest_ensure_response( $this->get_mau_data() );
        }
    }

    /**
     * 月間アクティブユーザー数のダミーデータを生成します。
     *
     * @return array
     */
    private function get_mau_data(): array {
        // 過去12か月の名前と値を生成
        $labels = [];
        $values = [];
        for ( $i = 11; $i >= 0; $i-- ) {
            $timestamp = strtotime( sprintf( '-%d months', $i ) );
            $labels[]  = date_i18n( 'M Y', $timestamp );
            // ダミー値：500〜800のランダムなユーザー数
            $values[]  = rand( 500, 800 );
        }
        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label' => __( 'Monthly Active Users', 'roro-core' ),
                    'data'  => $values,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor'     => 'rgba(75, 192, 192, 1)',
                    'borderWidth'     => 1,
                ],
            ],
        ];
    }

    /**
     * 月毎の収益のダミーデータを生成します。
     *
     * @return array
     */
    private function get_revenue_data(): array {
        $labels = [];
        $values = [];
        for ( $i = 11; $i >= 0; $i-- ) {
            $timestamp = strtotime( sprintf( '-%d months', $i ) );
            $labels[]  = date_i18n( 'M Y', $timestamp );
            // ダミー値：10,000〜20,000のランダムな収益
            $values[]  = rand( 10000, 20000 );
        }
        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label' => __( 'Monthly Revenue (¥)', 'roro-core' ),
                    'data'  => $values,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor'     => 'rgba(255, 99, 132, 1)',
                    'borderWidth'     => 1,
                ],
            ],
        ];
    }
}
