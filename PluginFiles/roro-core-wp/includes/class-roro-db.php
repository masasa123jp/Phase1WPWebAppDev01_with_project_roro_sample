<?php

/**
 * Handle database-related operations for the Roro Core plugin.
 *
 * This class is a centralized location for defining the names of your custom
 * tables and encapsulates methods for reading and writing data to those
 * tables. As you flesh out your plugin's functionality you can add more
 * methods here (for example, CRUD functions for different entities).
 *
 * @since 1.0.0
 * @package RoroCoreWp
 */
class Roro_Core_Wp_DB {
    /**
     * Retrieve the full table name for a given identifier.
     *
     * @param string $name Short table suffix without prefix and namespace.
     * @return string Fully qualified table name.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'roro_' . $name;
    }

    /**
     * Fetch all events.
     *
     * @return array[] List of event rows.
     */
    public static function get_events() {
        global $wpdb;
        $table = self::table( 'event_master' );
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY start_date ASC", ARRAY_A );
    }

    /**
     * Fetch all travel spots.
     *
     * @return array[] List of travel spot rows.
     */
    public static function get_spots() {
        global $wpdb;
        $table = self::table( 'travel_spot_master' );
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );
    }

    /**
     * Fetch a random advice entry for a given language.  Falls back to NULL if none exist.
     *
     * @param string $language Language code.
     * @return array|null Advice row or null.
     */
    public static function get_random_advice( $language = 'ja' ) {
        global $wpdb;
        $table = self::table( 'one_point_advice_master' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE language = %s ORDER BY RAND() LIMIT 1", $language ), ARRAY_A );
        return $row;
    }

    /**
     * Retrieve all favourites for a given user.  Returns an array of (item_id, item_type).
     *
     * @param int $user_id WordPress user ID.
     * @return array[] List of favourites.
     */
    public static function get_favourites_for_user( $user_id ) {
        global $wpdb;
        $table = self::table( 'map_favorite' );
        return $wpdb->get_results( $wpdb->prepare( "SELECT item_id, item_type FROM $table WHERE wp_user_id = %d", $user_id ), ARRAY_A );
    }

    /**
     * Toggle a favourite for a user.  If the pair exists it will be removed; otherwise inserted.
     * Returns an associative array with 'action' => 'added' or 'removed'.
     *
     * @param int    $user_id   User ID.
     * @param int    $item_id   ID of the item.
     * @param string $item_type Type of the item ('event' or 'spot').
     * @return array            Information about the change.
     */
    public static function toggle_favourite( $user_id, $item_id, $item_type ) {
        global $wpdb;
        $table = self::table( 'map_favorite' );
        // Check if existing
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE wp_user_id = %d AND item_id = %d AND item_type = %s", $user_id, $item_id, $item_type ) );
        if ( $existing ) {
            $wpdb->delete( $table, array( 'id' => $existing ), array( '%d' ) );
            return array( 'action' => 'removed' );
        }
        $wpdb->insert( $table, array( 'wp_user_id' => $user_id, 'item_id' => $item_id, 'item_type' => $item_type ), array( '%d', '%d', '%s' ) );
        return array( 'action' => 'added' );
    }

    /**
     * Create a new AI conversation or retrieve an existing one for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return int Conversation ID.
     */
    public static function get_or_create_conversation( $user_id ) {
        global $wpdb;
        $table = self::table( 'ai_conversation' );
        // Attempt to find a conversation from the last 24 hours to reuse.
        $conversation_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE wp_user_id = %d AND started_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY started_at DESC LIMIT 1", $user_id ) );
        if ( $conversation_id ) {
            return (int) $conversation_id;
        }
        // Create new conversation.
        $wpdb->insert( $table, array( 'wp_user_id' => $user_id ), array( '%d' ) );
        return (int) $wpdb->insert_id;
    }

    /**
     * Insert a message into a conversation.
     *
     * @param int    $conversation_id Conversation ID.
     * @param string $role            Role of sender ('user' or 'bot').
     * @param string $message         Content of the message.
     * @param int|null $user_id       WordPress user ID (for user messages).  Optional for bot messages.
     * @return void
     */
    public static function insert_conversation_message( $conversation_id, $role, $message, $user_id = null ) {
        global $wpdb;
        $table = self::table( 'ai_message' );
        $data  = array(
            'conversation_id' => $conversation_id,
            'role'            => $role,
            'message'         => $message,
        );
        $format = array( '%d', '%s', '%s' );
        if ( ! is_null( $user_id ) ) {
            $data['wp_user_id'] = $user_id;
            $format[]           = '%d';
        }
        $wpdb->insert( $table, $data, $format );
    }
}