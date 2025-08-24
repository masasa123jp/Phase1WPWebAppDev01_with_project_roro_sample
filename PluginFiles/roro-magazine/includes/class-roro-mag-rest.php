<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Mag_REST {
    public function register_routes(){
        register_rest_route('roro/v1', '/mag/issues', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'issues'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => ['type'=>'integer','required'=>false,'default'=>6],
                'offset'=> ['type'=>'integer','required'=>false,'default'=>0],
            ]
        ]);
        register_rest_route('roro/v1', '/mag/issue/(?P<id>\d+)/articles', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'articles'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function issues(WP_REST_Request $req){
        $svc = new RORO_Mag_Service();
        $lang = $svc->detect_lang();
        $M    = $svc->load_lang($lang);
        $limit = intval($req->get_param('limit') ?: 6);
        $offset= intval($req->get_param('offset') ?: 0);
        $posts = $svc->list_issues($limit, $offset);
        $items = array_map(function($p) use ($svc, $lang){ return $svc->issue_payload($p, $lang); }, $posts);
        return new WP_REST_Response(['items'=>$items], 200);
    }

    public function articles(WP_REST_Request $req){
        $svc = new RORO_Mag_Service();
        $lang = $svc->detect_lang();
        $issue_id = intval($req['id']);
        $posts = $svc->list_articles_by_issue($issue_id);
        $items = array_map(function($p) use ($svc, $lang){ return $svc->article_payload($p, $lang); }, $posts);
        return new WP_REST_Response(['items'=>$items], 200);
    }
}
