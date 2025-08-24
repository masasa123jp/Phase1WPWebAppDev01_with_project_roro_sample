<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Advice_REST {
    public function register_routes(){
        register_rest_route('roro/v1', '/advice/random', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'random'],
            'permission_callback' => '__return_true',
            'args' => [
                'category' => ['type'=>'string','required'=>false],
            ]
        ]);
    }
    public function random(WP_REST_Request $req){
        $svc = new RORO_Advice_Service();
        $ad  = $svc->get_random_advice( $req->get_param('category') );
        return new WP_REST_Response(['advice'=>$ad], 200);
    }
}
