<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/download_data_endpoint.php
 *
 * データダウンロードエンドポイント。
 * 管理者が指定されたデータセットをCSV形式でダウンロードできます。未指定の場合はガチャログを出力します。
 * サポートするデータセット: gacha, revenue, payment, ad_click, report, customer。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Download_Data_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/analytics/download-data';

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
                'args'                => [
                    'dataset' => [ 'type' => 'string', 'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * データをCSV形式で出力して返す。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        $dataset = $request->get_param( 'dataset' ) ?: 'gacha';
        global $wpdb;
        // データセット名とテーブル・列のマッピング
        $mapping = [
            'gacha'    => [ $wpdb->prefix . 'roro_gacha_log', [ 'spin_id','customer_id','facility_id','advice_id','prize_type','price','sponsor_id','created_at' ] ],
            'revenue'  => [ $wpdb->prefix . 'roro_revenue',   [ 'rev_id','customer_id','amount','source','created_at' ] ],
            'payment'  => [ $wpdb->prefix . 'roro_payment',   [ 'payment_id','customer_id','sponsor_id','method','amount','status','created_at' ] ],
            'ad_click' => [ $wpdb->prefix . 'roro_ad_click',  [ 'click_id','ad_id','customer_id','clicked_at' ] ],
            'report'   => [ $wpdb->prefix . 'roro_report',    [ 'report_id','customer_id','content','created_at' ] ],
            'customer' => [ $wpdb->prefix . 'roro_customer',  [ 'customer_id','name','email','phone','zipcode','breed_id','birth_date','auth_provider','user_type','consent_status','created_at' ] ],
        ];
        if ( ! isset( $mapping[ $dataset ] ) ) {
            return new WP_Error( 'invalid_dataset', __( '無効なデータセットです。', 'roro-core' ), [ 'status' => 400 ] );
        }
        [ $table, $columns ] = $mapping[ $dataset ];
        $cols_sql = implode( ',', array_map( static function( $c ) { return esc_sql( $c ); }, $columns ) );
        $rows     = $wpdb->get_results( "SELECT {$cols_sql} FROM {$table}", ARRAY_A );
        // CSV生成
        $fh  = fopen( 'php://temp', 'r+' );
        fputcsv( $fh, $columns );
        foreach ( $rows as $row ) {
            $line = [];
            foreach ( $columns as $col ) {
                $val = $row[ $col ];
                if ( is_array( $val ) ) {
                    $val = wp_json_encode( $val );
                }
                $line[] = $val;
            }
            fputcsv( $fh, $line );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        $filename = sanitize_file_name( $dataset . '_' . date( 'Ymd_His' ) . '.csv' );
        $response = new WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv; charset=utf-8' );
        $response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
        return $response;
    }
}
