<?php
/**
 * Event search helper.
 *
 * This class provides a simple interface for retrieving upcoming events
 * from custom post types or post meta. It can be used by fallback
 * responses to supply relevant suggestions based on the user's query.
 *
 * @package RORO_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class RORO_Chat_Events
 */
final class RORO_Chat_Events {

    /**
     * Search events based on keyword, pet type and date range.
     *
     * @param array<string,mixed> $args {
     *     Optional arguments to control the search.
     *
     *     @type string $keyword     A search term to match against titles or content.
     *     @type string $pet         Filter by pet type meta (e.g. 'dog', 'cat').
     *     @type string $start_date  Start date in Y-m-d format.
     *     @type string $end_date    End date in Y-m-d format.
     *     @type int    $numberposts Number of posts to return.
     * }
     * @return array<int,array<string,string>> A list of events with keys: title, url, date, location and excerpt.
     */
    public function search_events( array $args = [] ): array {
        $defaults = [
            'keyword'     => '',
            'pet'         => '',
            'start_date'  => '',
            'end_date'    => '',
            'numberposts' => 5,
        ];
        $args = wp_parse_args( $args, $defaults );

        // Determine which post type to search for events. Preference: roro_event → event → post.
        $post_type = post_type_exists( 'roro_event' ) ? 'roro_event' : ( post_type_exists( 'event' ) ? 'event' : 'post' );

        $query_args = [
            'post_type'      => $post_type,
            'posts_per_page' => (int) $args['numberposts'],
            'post_status'    => 'publish',
            's'              => $args['keyword'],
            'orderby'        => 'meta_value',
            'meta_key'       => 'event_date',
            'order'          => 'ASC',
        ];

        // Build date query if specified.
        if ( $args['start_date'] || $args['end_date'] ) {
            $date_query = [];
            if ( $args['start_date'] ) {
                $date_query['after'] = $args['start_date'];
            }
            if ( $args['end_date'] ) {
                $date_query['before'] = $args['end_date'];
            }
            $query_args['date_query'] = [ $date_query ];
        }

        // Meta query for pet type and ensuring event_date exists.
        $meta_query = [];
        if ( $args['pet'] ) {
            $meta_query[] = [
                'key'     => 'pet_type',
                'value'   => $args['pet'],
                'compare' => 'LIKE',
            ];
        }
        // Always ensure event_date exists if ordering by it.
        $meta_query[] = [
            'key'     => 'event_date',
            'compare' => 'EXISTS',
        ];
        if ( $meta_query ) {
            $query_args['meta_query'] = $meta_query;
        }

        $query  = new WP_Query( $query_args );
        $events = [];
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $events[] = [
                    'title'    => get_the_title( $post ),
                    'url'      => get_permalink( $post ),
                    'date'     => get_post_meta( $post->ID, 'event_date', true ),
                    'location' => get_post_meta( $post->ID, 'event_location', true ),
                    'excerpt'  => wp_trim_words( $post->post_content, 30, '…' ),
                ];
            }
        }
        wp_reset_postdata();
        return $events;
    }

    /**
     * Attempt to infer pet type from a user message.
     *
     * This rudimentary implementation checks for Japanese and English
     * keywords for dogs and cats. Additional patterns can be added as
     * needed.
     *
     * @param string $message User input.
     * @return string Detected pet type or empty string if none.
     */
    public function detect_pet_type( string $message ): string {
        $msg = mb_strtolower( $message );
        if ( strpos( $msg, '犬' ) !== false || strpos( $msg, 'dog' ) !== false ) {
            return 'dog';
        }
        if ( strpos( $msg, '猫' ) !== false || strpos( $msg, 'cat' ) !== false ) {
            return 'cat';
        }
        // Add further patterns for rabbits, birds etc. as needed.
        return '';
    }
}