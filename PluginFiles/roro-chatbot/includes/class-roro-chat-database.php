<?php
/**
 * Database access layer for the RORO Chatbot plugin.
 *
 * This class encapsulates the logic for creating and retrieving
 * conversation records and message history. Separating database access
 * concerns into its own module makes it easier to maintain and test the
 * conversation persistence logic independently from the chat service
 * itself. All methods are static because there is no per-instance state.
 *
 * Tables used:
 * - RORO_AI_CONVERSATION: stores each conversation. Expected columns:
 *   id (auto-increment), customer_id, title, created_at.
 * - RORO_AI_MESSAGE: stores messages in conversations. Expected columns:
 *   id, conversation_id, role (user/assistant), content, created_at.
 * Note that these tables are assumed to exist; the plugin does not create
 * them and will silently operate if they are missing.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Database {
    /**
     * Number of past messages to include when sending context to the provider.
     */
    const HISTORY_LIMIT = 8;

    /**
     * Ensure a conversation exists and return its ID.
     *
     * If a valid conversation ID is provided it will simply be returned.
     * Otherwise a new record is created in the `RORO_AI_CONVERSATION` table
     * using the current user (or provided user) to look up the customer_id
     * through the RORO_USER_LINK_WP linking table. If the linking table
     * query yields no result the customer_id will be stored as NULL.
     *
     * @param int $conv_id Conversation ID or 0 to create a new one.
     * @param int $user_id Optional WordPress user ID; defaults to current user.
     * @return int Conversation ID.
     */
    public static function ensure_conversation(int $conv_id, int $user_id): int {
        global $wpdb;
        if ($conv_id > 0) {
            return $conv_id;
        }
        $customer_id = null;
        // Determine the current user if not provided
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        // Look up linked customer_id via RORO_USER_LINK_WP mapping table
        if ($user_id) {
            $customer_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d",
                    $user_id
                )
            );
        }
        // Insert new conversation
        $wpdb->insert(
            'RORO_AI_CONVERSATION',
            [
                'customer_id' => $customer_id ?: null,
                'title'       => 'Chat',
                'created_at'  => current_time('mysql', true),
            ],
            ['%d','%s','%s']
        );
        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve recent history for a conversation.
     *
     * Messages are returned as an array of arrays with keys `role` and
     * `content` ordered from oldest to newest. Only the most recent
     * HISTORY_LIMIT messages are returned. If no history exists an empty
     * array is returned.
     *
     * @param int $conv_id Conversation ID.
     * @return array<int,array{role:string,content:string}> Message history.
     */
    public static function get_recent_history(int $conv_id): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content FROM RORO_AI_MESSAGE WHERE conversation_id=%d ORDER BY id DESC LIMIT %d",
                $conv_id,
                self::HISTORY_LIMIT
            ),
            ARRAY_A
        );
        if (!$rows) {
            return [];
        }
        $rows = array_reverse($rows);
        $messages = [];
        foreach ($rows as $r) {
            $messages[] = [
                'role'    => (string) $r['role'],
                'content' => (string) $r['content'],
            ];
        }
        return $messages;
    }

    /**
     * Persist a chat message to the database.
     *
     * If the underlying table does not exist the insertion will be attempted
     * and silently fail. Errors are deliberately suppressed because chat
     * persistence is optional. Roles should be `user` or `assistant`.
     *
     * @param int    $conv_id Conversation ID.
     * @param string $role     Role of the message author.
     * @param string $content  Message content.
     * @return void
     */
    public static function log_message(int $conv_id, string $role, string $content): void {
        global $wpdb;
        $wpdb->insert(
            'RORO_AI_MESSAGE',
            [
                'conversation_id' => $conv_id,
                'role'            => $role,
                'content'         => $content,
                'created_at'      => current_time('mysql', true),
            ],
            ['%d','%s','%s','%s']
        );
    }
}