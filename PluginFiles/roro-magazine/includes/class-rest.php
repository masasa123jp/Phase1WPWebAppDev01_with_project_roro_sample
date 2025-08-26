<?php
/**
 * REST API controller for the RORO magazine.  The REST layer
 * exposes issue and article listings in a format suitable for
 * consumption by JavaScript applications or third party services.
 * Each endpoint is publicly readable and does not require
 * authentication.  Responses are localised according to the
 * detected language of the current request.
 */
class RORO_Mag_Rest {

    /**
     * Register REST endpoints under the `roro/v1` namespace.  Two
     * routes are provided:
     *  - `/mag/issues`: list issues with optional limit and offset
     *  - `/mag/issue/<id>/articles`: list articles within a given
     *    issue
     */
    public function register_routes(): void {
        register_rest_route('roro/v1', '/mag/issues', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => [$this, 'issues'],
            'args'                => [
                'limit'  => [ 'type' => 'integer', 'default' => 6 ],
                'offset' => [ 'type' => 'integer', 'default' => 0 ],
            ],
        ]);
        register_rest_route('roro/v1', '/mag/issue/(?P<id>\d+)/articles', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => [$this, 'articles'],
        ]);
    }

    /**
     * REST callback for listing issues.  Applies limit and offset
     * parameters and returns an array of issue payloads.
     */
    public function issues(WP_REST_Request $request): WP_REST_Response {
        $svc    = new RORO_Mag_Service();
        $lang   = $svc->detect_lang();
        $limit  = intval($request->get_param('limit'));
        $offset = intval($request->get_param('offset'));
        $posts  = $svc->list_issues($limit, $offset);
        $items  = [];
        foreach ($posts as $p) {
            $items[] = $svc->issue_payload($p, $lang);
        }
        return new WP_REST_Response([ 'items' => $items ], 200);
    }

    /**
     * REST callback for listing articles within a specific issue.  The
     * issue ID is extracted from the route parameters.  Returns an
     * array of article payloads.
     */
    public function articles(WP_REST_Request $request): WP_REST_Response {
        $svc      = new RORO_Mag_Service();
        $lang     = $svc->detect_lang();
        $issue_id = intval($request['id']);
        $posts    = $svc->list_articles_by_issue($issue_id);
        $items    = [];
        foreach ($posts as $p) {
            $items[] = $svc->article_payload($p, $lang);
        }
        return new WP_REST_Response([ 'items' => $items ], 200);
    }
}