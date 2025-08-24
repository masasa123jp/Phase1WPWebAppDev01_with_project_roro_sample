<?php
/**
 * RESTエンドポイント: /roro/v1/favorites
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Favorites_REST {

    public function register_routes() {
        register_rest_route('roro/v1', '/favorites', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_list' ],
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => [
                'target' => [
                    'type'        => 'string',
                    'required'    => false,
                    'description' => 'Filter by type: spot or event'
                ]
            ]
        ]);

        register_rest_route('roro/v1', '/favorites/add', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'post_add' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        register_rest_route('roro/v1', '/favorites/remove', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'delete_remove' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
    }

    public function get_list(WP_REST_Request $req) {
        $svc   = new RORO_Favorites_Service();
        $lang  = $svc->detect_lang();
        $target = $req->get_param('target');
        $list  = $svc->list_favorites(get_current_user_id(), $lang, $target);
        return new WP_REST_Response([ 'items' => $list ], 200);
    }

    public function post_add(WP_REST_Request $req) {
        $svc  = new RORO_Favorites_Service();
        $type = sanitize_text_field($req->get_param('target_type'));
        $id   = intval($req->get_param('target_id'));
        if (!$type || !$id) {
            return new WP_REST_Response([ 'error' => 'bad params' ], 400);
        }
        $result = $svc->add_favorite(get_current_user_id(), $type, $id);
        if (is_wp_error($result)) {
            return new WP_REST_Response([ 'error' => $result->get_error_message() ], 500);
        }
        return new WP_REST_Response([ 'result' => $result ], 200);
    }

    public function delete_remove(WP_REST_Request $req) {
        $svc  = new RORO_Favorites_Service();
        $type = sanitize_text_field($req->get_param('target_type'));
        $id   = intval($req->get_param('target_id'));
        if (!$type || !$id) {
            return new WP_REST_Response([ 'error' => 'bad params' ], 400);
        }
        $result = $svc->remove_favorite(get_current_user_id(), $type, $id);
        if (is_wp_error($result)) {
            return new WP_REST_Response([ 'error' => $result->get_error_message() ], 500);
        }
        return new WP_REST_Response([ 'result' => $result ], 200);
    }
}
