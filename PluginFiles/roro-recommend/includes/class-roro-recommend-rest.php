<?php
/**
 * RORO Recommend REST API - RESTエンドポイント定義
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Recommend_REST {

    /**
     * REST APIルートを登録（名前空間: roro/v1）
     */
    public function register_routes() {
        register_rest_route('roro/v1', '/recommend/today', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_today'),
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => array(
                'lang' => array(
                    'description' => '言語コード (ja|en|zh|ko)',
                    'type'        => 'string',
                    'required'    => false
                )
            )
        ));
        register_rest_route('roro/v1', '/recommend/regen', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'post_regen'),
            'permission_callback' => function() { return is_user_logged_in(); }
        ));
    }

    /**
     * RESTコールバック: 今日のおすすめを取得（現在ログイン中のユーザー）
     */
    public function get_today(WP_REST_Request $request) {
        $service = new RORO_Recommend_Service();
        $lang    = $request->get_param('lang') ? sanitize_text_field($request->get_param('lang')) : $service->detect_lang();
        $user_id = get_current_user_id();
        $result  = $service->get_today($user_id, $lang);
        return $result;  // 配列または null を返却（nullはJSONではnullとして返る）
    }

    /**
     * RESTコールバック: 今日のおすすめを再生成（現在ログイン中のユーザー）
     */
    public function post_regen(WP_REST_Request $request) {
        $service = new RORO_Recommend_Service();
        $lang    = $request->get_param('lang') ? sanitize_text_field($request->get_param('lang')) : $service->detect_lang();
        $user_id = get_current_user_id();
        $result  = $service->regen_today($user_id, $lang);
        return $result;
    }
}
