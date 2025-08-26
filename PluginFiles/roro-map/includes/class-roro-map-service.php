<?php
/**
 * Service layer for the RORO Map plugin.  This class encapsulates
 * reusable logic for detecting the current language, loading
 * translation dictionaries for the JavaScript, retrieving event
 * categories and performing event searches.  Separating this logic
 * from the REST controller keeps the controller thin and easier to
 * test.
 */
class Roro_Map_Service {

    /**
     * Detect the language to use based on a query parameter, cookie or
     * WordPress locale.  Supported languages are Japanese (ja),
     * English (en), Chinese (zh) and Korean (ko).  Defaults to English
     * if none match.  This method is used to decide which JS
     * translation file to load.
     *
     * @return string Language code
     */
    public function detect_lang() {
        $l = null;
        if ( isset( $_GET['roro_lang'] ) ) {
            $l = sanitize_text_field( wp_unslash( $_GET['roro_lang'] ) );
        } elseif ( isset( $_COOKIE['roro_lang'] ) ) {
            $l = sanitize_text_field( wp_unslash( $_COOKIE['roro_lang'] ) );
        } else {
            $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
            if ( strpos( $locale, 'ja' ) === 0 ) {
                $l = 'ja';
            } elseif ( strpos( $locale, 'zh' ) === 0 ) {
                $l = 'zh';
            } elseif ( strpos( $locale, 'ko' ) === 0 ) {
                $l = 'ko';
            } else {
                $l = 'en';
            }
        }
        return in_array( $l, [ 'ja', 'en', 'zh', 'ko' ], true ) ? $l : 'en';
    }

    /**
     * Load the JavaScript translation dictionary for the given language.
     * Translation files live in the plugin's lang directory and return
     * an associative array of message keys to strings.  If the
     * requested language cannot be loaded the English dictionary is
     * used as a fallback.
     *
     * @param string $lang Language code as returned from detect_lang().
     * @return array Associative array of translation strings.
     */
    public function load_lang( $lang ) {
        $file = RORO_MAP_PATH . 'lang/' . $lang . '.php';
        if ( file_exists( $file ) ) {
            include $file;
            if ( isset( $roro_events_messages ) && is_array( $roro_events_messages ) ) {
                return $roro_events_messages;
            }
        }
        // Fallback to English
        include RORO_MAP_PATH . 'lang/en.php';
        return isset( $roro_events_messages ) ? $roro_events_messages : [];
    }

    /**
     * Retrieve a list of event categories.  If a dedicated category
     * master table exists (prefixed with the current blog's prefix)
     * then it will be used; otherwise distinct category values from
     * event posts are returned.  Categories are returned as an array
     * of associative arrays with 'code' and 'name' keys.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return array[] List of categories
     */
    public function get_categories() {
        global $wpdb;
        $cat_tbl = $wpdb->prefix . 'RORO_EVENT_CATEGORY_MASTER';

        // Check if the category master table exists in the current database.
        $has_cat = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $cat_tbl ) );
        if ( $has_cat ) {
            // Use the master table contents.
            $rows = $wpdb->get_results( "SELECT category_code, category_name FROM {$cat_tbl} ORDER BY sort_order ASC, id ASC", ARRAY_A );
            $cats = [];
            foreach ( $rows as $r ) {
                $cats[] = [ 'code' => $r['category_code'], 'name' => $r['category_name'] ];
            }
            return $cats;
        }

        // Fallback: derive categories from the roro_event custom post type.
        $cats  = [];
        $codes = [];
        $posts = get_posts( [
            'post_type'      => 'roro_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'roro_cat',
            'fields'         => 'ids',
        ] );
        foreach ( $posts as $pid ) {
            $c = get_post_meta( $pid, 'roro_cat', true );
            if ( $c && ! in_array( $c, $codes, true ) ) {
                $codes[] = $c;
            }
        }
        sort( $codes, SORT_NATURAL | SORT_FLAG_CASE );
        foreach ( $codes as $c ) {
            $cats[] = [ 'code' => $c, 'name' => $c ];
        }
        return $cats;
    }

    /**
     * Search for events given a set of parameters.  The search will
     * respect keywords, categories, date range and distance filters and
     * returns a paginated list of events along with the total count.
     * When latitude and longitude are provided the distance to each
     * event will be calculated using the Haversine formula and results
     * can be ordered by distance.
     *
     * @param array $args Associative array of search parameters:
     *  - q (string)         Keyword to search for in title, content or address.
     *  - categories (array) List of category codes to filter by.
     *  - date_from (string) ISO date (YYYY-MM-DD) for start date.
     *  - date_to (string)   ISO date (YYYY-MM-DD) for end date.
     *  - lat (float)        Latitude for distance search.
     *  - lng (float)        Longitude for distance search.
     *  - radius_km (float)  Maximum radius in kilometres.
     *  - limit (int)        Maximum number of items to return.
     *  - offset (int)       Number of items to skip.
     *  - order_by (string)  'date' or 'distance'.
     *
     * @return array Contains 'items' (list of events) and 'total' (int)
     */
    public function search_events( $args ) {
        global $wpdb;
        // Pull out arguments with defaults.  Sanitize text fields where appropriate.
        $q          = isset( $args['q'] )          ? sanitize_text_field( wp_unslash( $args['q'] ) ) : '';
        $categories = isset( $args['categories'] ) ? (array) $args['categories'] : [];
        $date_from  = isset( $args['date_from'] )  ? sanitize_text_field( wp_unslash( $args['date_from'] ) ) : '';
        $date_to    = isset( $args['date_to'] )    ? sanitize_text_field( wp_unslash( $args['date_to'] ) ) : '';
        $lat        = isset( $args['lat'] )        ? floatval( $args['lat'] ) : null;
        $lng        = isset( $args['lng'] )        ? floatval( $args['lng'] ) : null;
        $radius_km  = isset( $args['radius_km'] )  ? floatval( $args['radius_km'] ) : 0.0;
        $limit      = isset( $args['limit'] )      ? max( 1, min( 200, intval( $args['limit'] ) ) ) : 100;
        $offset     = isset( $args['offset'] )     ? max( 0, intval( $args['offset'] ) ) : 0;
        $order_by   = isset( $args['order_by'] )   && $args['order_by'] === 'distance' ? 'distance' : 'date';

        // First check if a custom events master table exists.  If it does
        // we run a SQL query similar to the original plugin so that
        // existing data continues to work without requiring migration.
        $ev_tbl  = $wpdb->prefix . 'RORO_EVENTS_MASTER';
        $has_tbl = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $ev_tbl ) );
        if ( $has_tbl ) {
            // Build SQL conditions.
            $wheres = [ '1=1' ];
            $params = [];
            if ( $q ) {
                $wheres[] = "(title LIKE %s OR description LIKE %s OR address LIKE %s)";
                $like = '%' . $wpdb->esc_like( $q ) . '%';
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
            if ( ! empty( $categories ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $categories ), '%s' ) );
                $wheres[] = "category IN ($placeholders)";
                foreach ( $categories as $c ) {
                    $params[] = sanitize_text_field( $c );
                }
            }
            if ( $date_from ) {
                $wheres[] = "DATE(start_time) >= %s";
                $params[] = $date_from;
            }
            if ( $date_to ) {
                $wheres[] = "DATE(start_time) <= %s";
                $params[] = $date_to;
            }

            // Start constructing the SELECT clause.  We calculate distance
            // only when lat/lng are provided and a radius is specified.
            $select = "SELECT SQL_CALC_FOUND_ROWS id, title, description, start_time, end_time, category, latitude, longitude, address";
            $order_clause = " ORDER BY start_time ASC, id ASC";
            $having = "";
            if ( $lat !== null && $lng !== null && $radius_km > 0 ) {
                // Haversine formula in SQL.  The LEAST() call protects
                // against rounding errors that can result in values > 1.
                $select .= ", (6371 * ACOS( LEAST(1, COS(RADIANS(%f)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(latitude)) ))) ) AS distance_km";
                $select = sprintf( $select, $lat, $lng, $lat );
                $having = $wpdb->prepare( " HAVING distance_km <= %f", $radius_km );
                $order_clause = ( $order_by === 'distance' ) ? " ORDER BY distance_km ASC, start_time ASC" : $order_clause;
            }
            $sql  = $select . " FROM {$ev_tbl} WHERE " . implode( ' AND ', $wheres ) . $having . $order_clause;
            $sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $limit, $offset );
            $prepared = $wpdb->prepare( $sql, $params );
            $rows = $wpdb->get_results( $prepared, ARRAY_A );
            $total = intval( $wpdb->get_var( "SELECT FOUND_ROWS()" ) );
            $items = [];
            foreach ( $rows as $r ) {
                $items[] = [
                    'id'          => intval( $r['id'] ),
                    'title'       => $r['title'],
                    'description' => $r['description'],
                    'start_time'  => $r['start_time'],
                    'end_time'    => $r['end_time'],
                    'category'    => $r['category'],
                    'latitude'    => floatval( $r['latitude'] ),
                    'longitude'   => floatval( $r['longitude'] ),
                    'address'     => $r['address'],
                    'distance_km' => isset( $r['distance_km'] ) ? floatval( $r['distance_km'] ) : null,
                ];
            }
            return [ 'items' => $items, 'total' => $total ];
        }

        // Otherwise fall back to querying the custom post type.  Build a
        // meta query to reduce the number of posts fetched from the DB.
        $meta_query = [ 'relation' => 'AND' ];
        if ( ! empty( $categories ) ) {
            $meta_query[] = [
                'key'     => 'roro_cat',
                'value'   => array_map( 'sanitize_text_field', $categories ),
                'compare' => 'IN',
            ];
        }
        if ( $date_from ) {
            $meta_query[] = [
                'key'     => 'roro_start',
                'value'   => $date_from,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ];
        }
        if ( $date_to ) {
            $meta_query[] = [
                'key'     => 'roro_start',
                'value'   => $date_to,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ];
        }

        $query_args = [
            'post_type'      => 'roro_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            's'              => $q ?: '',
            'meta_query'     => $meta_query,
        ];
        $query = new WP_Query( $query_args );
        $events = [];
        foreach ( $query->posts as $post ) {
            // Manual search across the address meta because WP_Query does not search meta fields.
            if ( $q ) {
                $addr = get_post_meta( $post->ID, 'roro_address', true );
                $combined = strtolower( $post->post_title . ' ' . $post->post_content . ' ' . $addr );
                if ( strpos( $combined, strtolower( $q ) ) === false ) {
                    continue;
                }
            }
            $start_time = get_post_meta( $post->ID, 'roro_start', true );
            $end_time   = get_post_meta( $post->ID, 'roro_end', true );
            $cat        = get_post_meta( $post->ID, 'roro_cat', true );
            $ev_lat     = floatval( get_post_meta( $post->ID, 'roro_lat', true ) );
            $ev_lng     = floatval( get_post_meta( $post->ID, 'roro_lng', true ) );
            $address    = get_post_meta( $post->ID, 'roro_address', true );
            // Compute distance if lat/lng search is provided.
            $distance = null;
            if ( $lat !== null && $lng !== null && $ev_lat && $ev_lng ) {
                $distance = $this->haversine( $lat, $lng, $ev_lat, $ev_lng );
                if ( $radius_km > 0 && $distance > $radius_km ) {
                    continue; // Skip events outside the radius.
                }
            }
            $events[] = [
                'id'          => (int) $post->ID,
                'title'       => get_the_title( $post ),
                'description' => wp_trim_words( strip_tags( $post->post_content ), 40, '...' ),
                'start_time'  => $start_time,
                'end_time'    => $end_time,
                'category'    => $cat,
                'latitude'    => $ev_lat,
                'longitude'   => $ev_lng,
                'address'     => $address,
                'distance_km' => $distance,
            ];
        }
        // Sort by distance or date.
        if ( $order_by === 'distance' && $lat !== null && $lng !== null ) {
            usort( $events, function( $a, $b ) {
                $da = isset( $a['distance_km'] ) ? $a['distance_km'] : PHP_INT_MAX;
                $db = isset( $b['distance_km'] ) ? $b['distance_km'] : PHP_INT_MAX;
                return $da <=> $db;
            } );
        } else {
            usort( $events, function( $a, $b ) {
                return strcmp( $a['start_time'], $b['start_time'] );
            } );
        }
        $total = count( $events );
        $events = array_slice( $events, $offset, $limit );
        return [ 'items' => $events, 'total' => $total ];
    }

    /**
     * Compute the great‑circle distance between two points on Earth using
     * the Haversine formula.  All parameters are expected to be
     * floating point numbers.
     *
     * @param float $lat1 Latitude of the first point in degrees.
     * @param float $lon1 Longitude of the first point in degrees.
     * @param float $lat2 Latitude of the second point in degrees.
     * @param float $lon2 Longitude of the second point in degrees.
     * @return float Distance in kilometres.
     */
    private function haversine( $lat1, $lon1, $lat2, $lon2 ) {
        $earthRadius = 6371; // Earth's radius in km
        $dLat        = deg2rad( $lat2 - $lat1 );
        $dLon        = deg2rad( $lon2 - $lon1 );
        $a           = sin( $dLat / 2 ) * sin( $dLat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dLon / 2 ) * sin( $dLon / 2 );
        $c           = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
        return $earthRadius * $c;
    }
}