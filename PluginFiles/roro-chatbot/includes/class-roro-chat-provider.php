<?php
/**
 * Base class for chat providers.
 *
 * Providers encapsulate the details of communicating with external services
 * such as OpenAI or Dify. Each provider subclass implements the
 * send() method to return a reply and optional metadata. Having a
 * unified interface allows the chat service to switch between providers
 * without duplicating logic. Providers should be stateless – all
 * configuration is passed to the constructor.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

abstract class RORO_Chat_Provider {

    /**
     * Send a message to the underlying chat provider and return a reply.
     *
     * The history parameter contains up to the most recent N messages in
     * chronological order. Providers may ignore history or append it as
     * context depending on their API semantics. The returned array must
     * contain a `reply` key and may include additional information in
     * a `meta` key.
     *
     * @param string $message The user's question.
     * @param array  $history Message history as arrays with `role` and `content`.
     * @return array<string,mixed> Array with keys `reply` and optionally `meta`.
     */
    abstract public function send(string $message, array $history): array;
}