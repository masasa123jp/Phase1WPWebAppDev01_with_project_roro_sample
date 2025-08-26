<?php
/**
 * OpenAI chat provider implementation.
 *
 * This provider uses OpenAI's Chat Completions API to generate replies. It
 * accepts a secret API key and a model name via the constructor. When
 * invoked, it sends the user message and the recent conversation history
 * to the OpenAI endpoint and returns the generated assistant reply. Errors
 * from wp_remote_post() are captured and returned via the `meta` field.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Provider_OpenAI extends RORO_Chat_Provider {
    /** @var string API key for the OpenAI service */
    private string $key;
    /** @var string Model name, e.g. gpt-4o-mini */
    private string $model;

    /**
     * Constructor.
     *
     * @param string $key   API key for OpenAI.
     * @param string $model Name of the model to use.
     */
    public function __construct(string $key, string $model = 'gpt-4o-mini') {
        $this->key   = $key;
        $this->model = $model ?: 'gpt-4o-mini';
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message, array $history): array {
        if ('' === $this->key) {
            return ['reply' => '', 'meta' => ['error' => 'invalid_openai_key']];
        }
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        // Prepend a system prompt to set the assistant's persona and guidelines.
        $messages = array_merge(
            [
                ['role' => 'system', 'content' => 'You are a helpful pet-care assistant. Avoid medical or legal advice.'],
            ],
            $history,
            [
                ['role' => 'user', 'content' => $message],
            ]
        );
        $payload = [
            'model'      => $this->model,
            'messages'   => $messages,
            'temperature'=> 0.7,
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
            $reply = $json['choices'][0]['message']['content'] ?? '';
            return ['reply' => (string) $reply, 'meta' => $json];
        }
        return ['reply' => '', 'meta' => ['error' => 'http_' . $code, 'raw' => $body]];
    }
}