<?php
/**
 * Core chat service combining persistence, provider selection and fallback.
 *
 * The chat service is responsible for orchestrating a user query from
 * reception through to response generation. It uses the database layer
 * (RORO_Chat_Database) to persist and retrieve conversation history and
 * leverages provider classes (OpenAI, Dify) or the fallback generator
 * depending on administrator settings. The service returns a uniform
 * response containing the conversation ID, the reply text and any
 * additional metadata returned by the provider.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Service {
    /**
     * Event helper instance for fallback suggestions.
     *
     * @var RORO_Chat_Events|null
     */
    private $events;

    /**
     * Constructor.
     *
     * Loads the events helper so that fallback replies can be enriched with
     * upcoming event suggestions. If the helper file cannot be found,
     * the property remains null and no suggestions will be added.
     */
    public function __construct() {
        $this->events = null;
        // Attempt to include the events class.
        if ( defined( 'RORO_CHAT_PATH' ) ) {
            $path = trailingslashit( RORO_CHAT_PATH ) . 'includes/class-roro-chat-events.php';
            if ( file_exists( $path ) ) {
                require_once $path;
                if ( class_exists( 'RORO_Chat_Events' ) ) {
                    $this->events = new RORO_Chat_Events();
                }
            }
        }
    }
    /**
     * Proxy to the language detection helper for backward compatibility.
     *
     * @return string
     */
    public function detect_lang(): string {
        return RORO_Chat_I18n::detect_lang();
    }

    /**
     * Proxy to load translation messages.
     *
     * @param string $lang
     * @return array<string,string>
     */
    public function load_lang(string $lang): array {
        return RORO_Chat_I18n::load_messages($lang);
    }

    /**
     * Handle a user message and return a reply.
     *
     * This method performs minimal validation on the user message, ensures
     * there is a conversation record, logs the user message, selects the
     * appropriate provider based on stored options and either sends the
     * message to that provider or falls back to the rule-based generator.
     * The assistant's reply is persisted and the entire response is
     * returned to the caller.
     *
     * @param string $message      User's message.
     * @param int    $conversation_id Conversation ID if continuing a session.
     * @param int    $user_id      WordPress user ID; defaults to current user.
     * @return array<string,mixed> Response data containing conversation_id, reply and optional meta information or error.
     */
    public function handle_user_message(string $message, int $conversation_id = 0, int $user_id = 0): array {
        $clean = trim($message);
        if ($clean === '') {
            return ['error' => 'empty_message'];
        }
        // Determine or create conversation
        $conv_id = RORO_Chat_Database::ensure_conversation($conversation_id, $user_id);
        // Load recent history
        $history = RORO_Chat_Database::get_recent_history($conv_id);
        // Persist the user's message
        RORO_Chat_Database::log_message($conv_id, 'user', $clean);
        $provider = get_option('roro_chat_provider', 'echo');
        $reply    = '';
        $meta     = [];
        if ($provider === 'openai') {
            $key   = get_option('roro_chat_openai_api_key', '');
            $model = get_option('roro_chat_openai_model', 'gpt-4o-mini');
            $prov  = new RORO_Chat_Provider_OpenAI($key, $model);
            $res   = $prov->send($clean, $history);
            $reply = $res['reply'] ?? '';
            $meta  = $res['meta']  ?? [];
        } elseif ($provider === 'dify') {
            $key  = get_option('roro_chat_dify_api_key', '');
            $base = get_option('roro_chat_dify_base', '');
            $prov = new RORO_Chat_Provider_Dify($base, $key);
            $res  = $prov->send($clean, $history);
            $reply = $res['reply'] ?? '';
            $meta  = $res['meta']  ?? [];
        } else {
            // Fallback: simple rule‑based response with optional event suggestions.
            $reply = RORO_Chat_Fallback::generate_reply( $clean );
            // If event helper is available, append upcoming events relevant to the message.
            if ( $this->events instanceof RORO_Chat_Events ) {
                $pet_type = $this->events->detect_pet_type( $clean );
                $events   = $this->events->search_events( [
                    'keyword'     => $clean,
                    'pet'         => $pet_type,
                    'start_date'  => date( 'Y-m-d' ),
                    'end_date'    => date( 'Y-m-d', strtotime( '+30 days' ) ),
                    'numberposts' => 3,
                ] );
                if ( ! empty( $events ) ) {
                    // Build a localized header line using translation key.
                    $header = __( 'suggest_events', 'roro-chatbot' );
                    $lines  = [];
                    foreach ( $events as $ev ) {
                        $date_str  = $ev['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $ev['date'] ) ) : '';
                        $location  = $ev['location'] ? sprintf( __( 'at', 'roro-chatbot' ), $ev['location'] ) : '';
                        $lines[]   = sprintf( '%s (%s%s) %s', $ev['title'], $date_str, $location, $ev['url'] );
                    }
                    $reply .= "\n\n" . $header . "\n" . implode( "\n", $lines );
                }
            }
        }
        // Save assistant reply if available
        if ($reply !== '') {
            RORO_Chat_Database::log_message($conv_id, 'assistant', $reply);
        }
        return [
            'conversation_id' => $conv_id,
            'reply'           => $reply,
            'meta'            => $meta,
        ];
    }
}