<?php
/**
 * レポート解析エンドポイント。
 *
 * 入力された犬種ID、月齢、地域、課題IDの配列を用いて、簡易的な分析を行い結果を返します。
 * 主な出力は以下の通り：
 *  - summary: 入力内容をまとめた文字列
 *  - issues: 課題IDに対応する名称一覧
 *  - message: 簡易的な助言メッセージ
 *  - topFacilities: レビューの高い施設トップ3（名前、カテゴリ、平均評価）
 * 今後、AIモデルを用いた高度な分析に置き換える予定です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Report_Analysis_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/report/analysis';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => [ self::class, 'permission_callback' ],
                'args'                => [
                    'breed_id'  => [ 'type' => 'integer', 'required' => true ],
                    'age_month' => [ 'type' => 'integer', 'required' => true ],
                    'region'    => [ 'type' => 'string',  'required' => false ],
                    'issues'    => [ 'type' => 'array',   'required' => false, 'items' => [ 'type' => 'integer' ] ],
                ],
            ],
        ] );
    }

    /**
     * 認可チェック（認証済みなら許可）。
     *
     * 親クラスの `permission_callback()` のシグネチャに合わせ、
     * WP_REST_Request を受け取るようにします。戻り値の型指定も削除し、
     * PHP の LSP 違反による致命的エラーを回避します。
     *
     * @param WP_REST_Request $request
     * @return bool True if the current user is logged in.
     */
    public static function permission_callback( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    /**
     * 分析処理本体。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $breed_id  = (int) $request->get_param( 'breed_id' );
        $age_month = (int) $request->get_param( 'age_month' );
        $region    = sanitize_text_field( $request->get_param( 'region' ) );
        $issues    = (array) $request->get_param( 'issues' );
        // 課題名取得
        $issue_names = [];
        if ( ! empty( $issues ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $issues ), '%d' ) );
            $issue_table  = $wpdb->prefix . 'roro_issue';
            $names        = $wpdb->get_col( $wpdb->prepare( "SELECT name FROM {$issue_table} WHERE issue_id IN ($placeholders)", $issues ) );
            $issue_names  = $names ?: [];
        }
        // 高評価施設上位3件を求める
        $facility_table = $wpdb->prefix . 'roro_facility';
        $review_table   = $wpdb->prefix . 'roro_facility_review';
        $facilities     = $wpdb->get_results(
            "SELECT f.facility_id AS id, f.name, f.category, COALESCE(AVG(r.rating),0) AS avg_rating
               FROM {$facility_table} f
          LEFT JOIN {$review_table} r ON f.facility_id = r.facility_id
           GROUP BY f.facility_id
           ORDER BY avg_rating DESC, f.name
           LIMIT 3",
            ARRAY_A
        );
        $summary = sprintf( 'breed_id=%d, age_month=%d, region=%s', $breed_id, $age_month, $region );
        $message = __( 'これは簡易的な分析結果です。今後はAIを使った詳細な分析が導入される予定です。', 'roro-core' );
        return rest_ensure_response( [
            'summary'       => $summary,
            'issues'        => $issue_names,
            'message'       => $message,
            'topFacilities' => $facilities,
        ] );
    }
}
