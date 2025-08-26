<?php
/**
 * Dify chat provider implementation.
 *
 * This provider communicates with a Dify application using the REST API.
 * The constructor accepts the base URL and an API key. The Dify API
 * endpoint used here is `/v1/chat-messages`, which may differ depending on
 * the specific Dify application configuration. Only the user query is sent
 * – conversation history is currently ignored because Dify embeds its own
 * context management. This can be extended if needed by including the
 * history in the payload according to the Dify API specification.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Provider_Dify extends RORO_Chat_Provider {
    /** @var string Base URL of the Dify API (without trailing slash) */
    private string $base;
    /** @var string API key for authenticating requests */
    private string $key;

    /**
     * Constructor.
     *
     * @param string $base Base URL of the Dify API, e.g. https://api.dify.ai
     * @param string $key  API key for the Dify application.
     */
    public function __construct(string $base, string $key) {
        $this->base = rtrim($base ?: '', '/');
        $this->key  = $key;
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message, array $history): array {
        if ('' === $this->base || '' === $this->key) {
            return ['reply' => '', 'meta' => ['error' => 'invalid_dify_setting']];
        }
        $endpoint = $this->base . '/v1/chat-messages';
        $payload = [
            'inputs'        => new \stdClass(),
            'query'         => $message,
            'response_mode' => 'blocking',
            'user'          => (string) get_current_user_id(),
            // Additional keys such as conversation_id or files can be added here
        ];
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
            'body'    => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            return ['reply' => '', 'meta' => ['error' => $response->get_error_message()]];
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            $reply = $json['answer'] ?? ($json['output_text'] ?? '');
            return ['reply' => (string) $reply, 'meta' => $json];
        }
        return ['reply' => '', 'meta' => ['error' => 'http_' . $code, 'raw' => $body]];
    }
}