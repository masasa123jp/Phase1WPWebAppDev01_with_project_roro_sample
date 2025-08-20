<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/gacha_endpoint.php
 *
 * ガチャ API エンドポイント。
 * 種別 (species) とカテゴリ (category) と郵便番号 (zipcode) を受け取り、施設・アドバイス・イベント・教材・スポンサー広告
 * の候補からランダムに1件を抽選し、その結果を返します。抽選結果は roro_gacha_log テーブルに記録されます。
 * Phase 1.6 ではスポンサー広告の当選に対応し、price と sponsor_id を記録するようになっています。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Gacha_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/gacha';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [ self::class, 'handle' ],
            'permission_callback' => [ self::class, 'permission_callback' ],
            'args' => [
                'species'  => [ 'type' => 'string', 'required' => true ],
                'category' => [ 'type' => 'string', 'required' => true ],
                'zipcode'  => [ 'type' => 'string', 'required' => false ],
            ],
        ] );
    }

    /**
     * 認証チェック。ログインユーザーのみ利用可。
     */
    public static function permission_callback() : bool {
        return is_user_logged_in();
    }

    /**
     * ガチャ処理を実行する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) {
        global $wpdb;
        $user_id  = get_current_user_id();
        $species  = sanitize_text_field( $request->get_param( 'species' ) );
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $zipcode  = sanitize_text_field( $request->get_param( 'zipcode' ) );

        if ( ! in_array( $species, [ 'dog', 'cat' ], true ) ) {
            return new WP_Error( 'invalid_species', __( '種別が不正です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        if ( empty( $category ) ) {
            return new WP_Error( 'invalid_category', __( 'カテゴリは必須です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        // テーブル名
        $mapping_table  = $wpdb->prefix . 'category_zip_mapping';
        $facility_table = $wpdb->prefix . 'facility';
        $advice_table   = $wpdb->prefix . 'onepoint_advice';
        $event_table    = $wpdb->prefix . 'event';
        $material_table = $wpdb->prefix . 'material';

        // 郵便番号から施設ID・アドバイスコードを取得
        $facilities = [];
        $advices    = [];
        if ( $zipcode ) {
            $like_zip     = $wpdb->esc_like( $zipcode ) . '%';
            $mapping_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT facility_id, advice_code
                   FROM {$mapping_table}
                  WHERE species = %s AND category = %s AND zipcode LIKE %s",
                $species,
                $category,
                $like_zip
            ) );
            foreach ( $mapping_rows as $row ) {
                if ( $row->facility_id ) {
                    $facilities[] = (int) $row->facility_id;
                }
                if ( $row->advice_code ) {
                    $advices[] = sanitize_text_field( $row->advice_code );
                }
            }
        }
        // 候補リスト作成
        $candidates = [];
        // 施設候補
        if ( ! empty( $facilities ) ) {
            $placeholders        = implode( ',', array_fill( 0, count( $facilities ), '%d' ) );
            $facility_candidates = $wpdb->get_results( $wpdb->prepare(
                "SELECT facility_id AS id, name, 'facility' AS type FROM {$facility_table} WHERE facility_id IN ({$placeholders})",
                $facilities
            ), ARRAY_A );
            $candidates          = array_merge( $candidates, $facility_candidates );
        }
        // アドバイス候補
        if ( ! empty( $advices ) ) {
            $placeholders      = implode( ',', array_fill( 0, count( $advices ), '%s' ) );
            $advice_candidates = $wpdb->get_results( $wpdb->prepare(
                "SELECT advice_code AS id, title AS name, 'advice' AS type FROM {$advice_table} WHERE advice_code IN ({$placeholders})",
                $advices
            ), ARRAY_A );
            $candidates        = array_merge( $candidates, $advice_candidates );
        }
        // イベント候補
        $event_candidates = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.event_id AS id, e.title AS name, 'event' AS type
               FROM {$event_table} e
               JOIN {$facility_table} f ON e.facility_id = f.facility_id
              WHERE e.category = %s
                AND e.start_time >= NOW()
                AND f.species IN (%s, 'both')",
            $category,
            $species
        ), ARRAY_A );
        $candidates = array_merge( $candidates, $event_candidates );
        // 教材候補
        $material_candidates = $wpdb->get_results( $wpdb->prepare(
            "SELECT material_id AS id, title AS name, 'material' AS type
               FROM {$material_table}
              WHERE category = %s
                AND (target_species = %s OR target_species = 'both')",
            $category,
            $species
        ), ARRAY_A );
        $candidates = array_merge( $candidates, $material_candidates );
        // スポンサー広告候補 (Phase 1.6)
        $ad_table      = $wpdb->prefix . 'roro_ad';
        $sponsor_table = $wpdb->prefix . 'roro_sponsor';
        $ad_candidates = $wpdb->get_results(
            "SELECT a.ad_id AS id, s.sponsor_id, a.title AS name, a.price, 'ad' AS type
               FROM {$ad_table} a
               JOIN {$sponsor_table} s ON a.sponsor_id = s.sponsor_id
              WHERE a.status = 'active'
                AND (a.start_date IS NULL OR a.start_date <= CURDATE())
                AND (a.end_date   IS NULL OR a.end_date   >= CURDATE())",
            ARRAY_A
        );
        if ( ! empty( $ad_candidates ) ) {
            $candidates = array_merge( $candidates, $ad_candidates );
        }
        if ( empty( $candidates ) ) {
            return new WP_Error( 'no_candidates', __( '該当する候補がありません。', 'roro-core' ), [ 'status' => 404 ] );
        }
        // ランダムに1つ選択
        $selected = $candidates[ array_rand( $candidates ) ];
        // ログを記録
        $table     = $wpdb->prefix . 'roro_gacha_log';
        $price     = 0.0;
        $sponsor_id= null;
        $data      = [
            'customer_id' => $user_id,
            'prize_type'  => $selected['type'],
            'created_at'  => current_time( 'mysql' ),
        ];
        if ( $selected['type'] === 'facility' ) {
            $data['facility_id'] = (int) $selected['id'];
        } elseif ( $selected['type'] === 'advice' ) {
            $data['advice_id'] = (int) $selected['id'];
        } elseif ( $selected['type'] === 'ad' ) {
            $price      = isset( $selected['price'] ) ? (float) $selected['price'] : 0.0;
            $sponsor_id = isset( $selected['sponsor_id'] ) ? (int) $selected['sponsor_id'] : null;
        }
        $data['price']      = $price;
        $data['sponsor_id'] = $sponsor_id;
        // プレースホルダ定義
        $format = [];
        foreach ( array_keys( $data ) as $column ) {
            switch ( $column ) {
                case 'customer_id':
                case 'facility_id':
                case 'advice_id':
                case 'sponsor_id':
                    $format[] = '%d';
                    break;
                case 'price':
                    $format[] = '%f';
                    break;
                default:
                    $format[] = '%s';
                    break;
            }
        }
        $wpdb->insert( $table, $data, $format );
        return new WP_REST_Response( [ 'prize' => $selected ] );
    }
}
