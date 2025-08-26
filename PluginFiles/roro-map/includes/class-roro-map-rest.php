<?php
/**
 * REST API controller for the RORO Map plugin.  Routes are
 * registered under the roro-map/v1 namespace and provide access to
 * event categories and events themselves.  The controller delegates
 * data retrieval to the Roro_Map_Service to keep concerns separated.
 */
class Roro_Map_Rest {

    /**
     * Register all routes for this controller.  Should be called on
     * 'rest_api_init'.
     */
    public static function register_routes() {
        register_rest_route( 'roro-map/v1', '/event-categories', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_categories' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'roro-map/v1', '/events', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_events' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q'          => [ 'type' => 'string',  'required' => false ],
                'categories' => [ 'type' => 'array',   'required' => false ],
                'date_from'  => [ 'type' => 'string',  'required' => false ],
                'date_to'    => [ 'type' => 'string',  'required' => false ],
                'lat'        => [ 'type' => 'number',  'required' => false ],
                'lng'        => [ 'type' => 'number',  'required' => false ],
                'radius_km'  => [ 'type' => 'number',  'required' => false, 'default' => 0 ],
                'limit'      => [ 'type' => 'integer', 'required' => false, 'default' => 100 ],
                'offset'     => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
                'order_by'   => [ 'type' => 'string',  'required' => false, 'enum' => [ 'date', 'distance' ], 'default' => 'date' ],
            ],
        ] );
    }

    /**
     * Callback for the event-categories endpoint.  Returns a list of
     * categories using the service class.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function get_categories( WP_REST_Request $request ) {
        $svc  = new Roro_Map_Service();
        $cats = $svc->get_categories();
        return new WP_REST_Response( [ 'items' => $cats ], 200 );
    }

    /**
     * Callback for the events endpoint.  Returns a list of events
     * matching the provided filters along with the total count.  All
     * parameters are optional.
     *
     * @param WP_REST_Request $request The request object containing parameters.
     * @return WP_REST_Response
     */
    public static function get_events( WP_REST_Request $request ) {
        $svc = new Roro_Map_Service();
        $params = [
            'q'          => $request->get_param( 'q' ),
            'categories' => $request->get_param( 'categories' ),
            'date_from'  => $request->get_param( 'date_from' ),
            'date_to'    => $request->get_param( 'date_to' ),
            'lat'        => $request->get_param( 'lat' ),
            'lng'        => $request->get_param( 'lng' ),
            'radius_km'  => $request->get_param( 'radius_km' ),
            'limit'      => $request->get_param( 'limit' ),
            'offset'     => $request->get_param( 'offset' ),
            'order_by'   => $request->get_param( 'order_by' ) ?: 'date',
        ];
        $result = $svc->search_events( $params );
        return new WP_REST_Response( $result, 200 );
    }
}