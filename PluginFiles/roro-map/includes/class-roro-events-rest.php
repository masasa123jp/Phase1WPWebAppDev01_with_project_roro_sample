<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Events_REST {
    public function register_routes(){
        register_rest_route('roro/v1', '/event-categories', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'categories'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('roro/v1', '/events', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'events'],
            'permission_callback' => '__return_true',
            'args' => [
                'q'          => ['type'=>'string','required'=>false],
                'categories' => ['type'=>'array','required'=>false],
                'date_from'  => ['type'=>'string','required'=>false],
                'date_to'    => ['type'=>'string','required'=>false],
                'lat'        => ['type'=>'number','required'=>false],
                'lng'        => ['type'=>'number','required'=>false],
                'radius_km'  => ['type'=>'number','required'=>false, 'default'=>0],
                'limit'      => ['type'=>'integer','required'=>false,'default'=>100],
                'offset'     => ['type'=>'integer','required'=>false,'default'=>0],
                'order_by'   => ['type'=>'string','required'=>false,'enum'=>['date','distance'], 'default'=>'date'],
            ]
        ]);
    }

    public function categories(WP_REST_Request $req){
        $svc = new RORO_Events_Service();
        $cats = $svc->get_categories();
        return new WP_REST_Response(['items'=>$cats], 200);
    }

    public function events(WP_REST_Request $req){
        $svc = new RORO_Events_Service();
        $params = [
            'q'          => $req->get_param('q'),
            'categories' => $req->get_param('categories'),
            'date_from'  => $req->get_param('date_from'),
            'date_to'    => $req->get_param('date_to'),
            'lat'        => $req->get_param('lat'),
            'lng'        => $req->get_param('lng'),
            'radius_km'  => $req->get_param('radius_km'),
            'limit'      => $req->get_param('limit'),
            'offset'     => $req->get_param('offset'),
            'order_by'   => $req->get_param('order_by') ?: 'date',
        ];
        $res = $svc->search_events($params);
        return new WP_REST_Response($res, 200);
    }
}
