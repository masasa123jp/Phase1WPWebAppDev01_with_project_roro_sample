<?php
/**
 * Fallback rule-based response generator.
 *
 * When no external AI provider is configured or an API call fails this
 * class can be used to produce a simple, deterministic reply. The current
 * implementation searches the RORO event and advice tables for relevant
 * information. If any events match the user query (by title, description
 * or address) a short summary of up to five events is included. In
 * addition, a random one–point advice is appended if available. Should no
 * data be found a generic message prompting the user to try using
 * keywords is returned instead. Database tables referenced here are
 * assumed to be created by other parts of the RORO application.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Fallback {
    /**
     * Generate a response based on local data when AI is disabled.
     *
     * @param string $message User's question (unused in current implementation).
     * @return string A newline-separated message for the user.
     */
    public static function generate_reply(string $message): string {
        global $wpdb;
        // Lowercase copy of the message for potential future keyword detection
        $m   = mb_strtolower($message);
        $ans = [];
        // Search for events matching the query in title, description or address
        $ev_tbl = $wpdb->prefix . 'RORO_EVENTS_MASTER';
        $like   = '%' . $wpdb->esc_like($message) . '%';
        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, start_time, address FROM {$ev_tbl} WHERE title LIKE %s OR description LIKE %s OR address LIKE %s ORDER BY start_time ASC LIMIT 5",
                $like,
                $like,
                $like
            ),
            ARRAY_A
        );
        if ($events) {
            $ans[] = '見つかったイベント:';
            foreach ($events as $ev) {
                $ans[] = sprintf('- %s (%s) @ %s', $ev['title'], $ev['start_time'], $ev['address']);
            }
        }
        // Fetch a random one–point advice
        $ad_tbl = $wpdb->prefix . 'RORO_ONE_POINT_ADVICE_MASTER';
        $advice = $wpdb->get_var("SELECT advice_text FROM {$ad_tbl} ORDER BY RAND() LIMIT 1");
        if ($advice) {
            $ans[] = 'ワンポイントアドバイス: ' . $advice;
        }
        if (!$ans) {
            return 'ご質問ありがとうございます。現在の設定では外部AIが無効のため、簡易応答で対応しています。具体的なキーワード（例：『ドッグラン』『八王子』）でお試しください。';
        }
        return implode("\n", $ans);
    }
}