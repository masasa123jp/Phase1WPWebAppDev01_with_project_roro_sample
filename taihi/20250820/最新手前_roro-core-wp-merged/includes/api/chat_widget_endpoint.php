<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/chat_widget_endpoint.php
 *
 * シンプルなチャットウィジェット用エンドポイント。疑似AIとして、固定の応答テーブル
 * を使用して利用者のメッセージに回答します。GET メソッドは最新 10 件の会話履歴を返し、
 * POST メソッドは利用者からのメッセージを受け取り、適切な応答を返します。
 *
 * 実運用では会話履歴をデータベースに保存し、応答ロジックをより高度にすることが
 * 推奨されますが、無料かつ外部APIを使用しない条件のため、ここでは簡易的な実装
 * としています。
 *
 * @package RoroCore\Api
 */

declare( strict_types = 1 );

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * チャットウィジェットエンドポイント。
 */
class Chat_Widget_Endpoint {
    /** @var string RESTルート */
    public const ROUTE = '/chat';

    /**
     * コンストラクタでフックを登録します。
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * REST API ルートを登録します。
     */
    public function register_routes(): void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_messages' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'send_message' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'message' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );
    }

    /**
     * 最新の会話履歴を返します。現状では固定の配列を返すのみですが、将来的には
     * データベースやユーザーメタから取得するよう拡張可能です。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_messages( WP_REST_Request $request ): WP_REST_Response {
        // ダミーの会話履歴
        $history = [
            [ 'role' => 'assistant', 'content' => __( 'こんにちは！何かお困りですか？', 'roro-core' ) ],
            [ 'role' => 'user',      'content' => __( 'おすすめの犬種を教えてください。', 'roro-core' ) ],
            [ 'role' => 'assistant', 'content' => __( '例えばシバ犬はいかがでしょうか？', 'roro-core' ) ],
        ];
        return rest_ensure_response( $history );
    }

    /**
     * 利用者からのメッセージを処理し、適当な応答を返します。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function send_message( WP_REST_Request $request ) {
        $message = $request->get_param( 'message' );
        if ( ! is_string( $message ) || '' === trim( $message ) ) {
            return new WP_Error( 'invalid_message', __( 'Message must be a non-empty string.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $message = strtolower( $message );
        // 疑似AI応答テーブル
        $responses = [
            'hello'  => __( 'こんにちは！ご用件をお聞かせください。', 'roro-core' ),
            'breed'  => __( '犬種についての情報ですね。どの犬種に興味がありますか？', 'roro-core' ),
            'advice' => __( 'アドバイスが必要ですね。詳しく教えていただけますか？', 'roro-core' ),
        ];
        $reply = __( 'ご質問ありがとうございます。現在準備中です。', 'roro-core' );
        foreach ( $responses as $keyword => $text ) {
            if ( false !== strpos( $message, $keyword ) ) {
                $reply = $text;
                break;
            }
        }
        // 将来的にはここで会話履歴の保存などを行う
        return rest_ensure_response( [
            'role'    => 'assistant',
            'content' => $reply,
        ] );
    }
}
