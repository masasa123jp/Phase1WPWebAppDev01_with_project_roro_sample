<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Chat_REST {
    public function register_routes(){
        register_rest_route('roro/v1', '/chat', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'chat'],
            'permission_callback' => '__return_true',
            'args' => [
                'message' => ['type'=>'string','required'=>true],
                'conversation_id' => ['type'=>'integer','required'=>false],
            ]
        ]);
    }

    public function chat(WP_REST_Request $req){
        if (!wp_verify_nonce($req->get_header('x-wp-nonce'), 'wp_rest')) {
            return new WP_REST_Response(['error'=>'invalid_nonce'], 403);
        }
        $message = $req->get_param('message');
        $conv_id = intval($req->get_param('conversation_id'));
        $user_id = get_current_user_id();

        $svc = new RORO_Chat_Service();
        $res = $svc->handle_user_message($message, $conv_id, $user_id);
        return new WP_REST_Response($res, 200);
    }
}
