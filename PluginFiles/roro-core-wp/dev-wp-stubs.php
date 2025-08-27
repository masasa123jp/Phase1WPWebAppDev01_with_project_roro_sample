<?php
/**
 * DEV WP STUBS
 * - ローカルIDE（Intelephense等）の型補完・エラー抑止用スタブ。
 * - 本番/実行環境では WordPress コアが提供する実体がロードされるため、
 *   ここでは極小限のシグネチャ互換のみを定義する。
 *
 * このファイルは「require しても安全」なように副作用なしで定義のみ。
 * 既に本物の WP がロードされている場合は即リターンする。
 */
declare(strict_types=1);

if (defined('WPINC')) {
    // すでに WordPress 実体があるなら何もしない
    return;
}

// ------------------------------------------------------------
// 基本関数のダミー（副作用なし）
// ------------------------------------------------------------
if (!function_exists('__'))        { function __(string $t, string $d = 'default'): string { return $t; } }
if (!function_exists('_e'))        { function _e(string $t, string $d = 'default'): void { echo $t; } }
if (!function_exists('add_action')){ function add_action(string $hook, callable $cb, int $prio = 10, int $args = 1): void {} }
if (!function_exists('add_filter')){ function add_filter(string $hook, callable $cb, int $prio = 10, int $args = 1): void {} }
if (!function_exists('apply_filters')){ function apply_filters(string $tag, $value, ...$args) { return $value; } }
if (!function_exists('do_action')) { function do_action(string $tag, ...$args): void {} }

if (!function_exists('esc_html'))  { function esc_html(?string $t): string { return (string)$t; } }
if (!function_exists('esc_attr'))  { function esc_attr(?string $t): string { return (string)$t; } }
if (!function_exists('esc_url'))   { function esc_url(?string $t): string { return (string)$t; } }

if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str) { return is_string($str)? $str : ''; } }
if (!function_exists('sanitize_key'))        { function sanitize_key($key) { return is_string($key)? preg_replace('/[^a-z0-9_\-]/i','', $key) : ''; } }

if (!function_exists('get_option'))  { function get_option(string $k, $default = false) { return $default; } }
if (!function_exists('update_option')){ function update_option(string $k, $v): bool { return true; } }
if (!function_exists('add_option'))   { function add_option(string $k, $v, string $a = '', string $autoload = 'yes'): bool { return true; } }
if (!function_exists('delete_option')){ function delete_option(string $k): bool { return true; } }

if (!function_exists('register_activation_hook'))   { function register_activation_hook($f, callable $cb): void {} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook($f, callable $cb): void {} }

if (!function_exists('wp_json_encode')) { function wp_json_encode($data, int $flags = 0, int $depth = 512): string { return json_encode($data, $flags); } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(): bool { return false; } }
if (!function_exists('current_user_can'))  { function current_user_can(string $cap): bool { return false; } }

if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($n, $a = -1) { return true; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a = -1): string { return 'nonce'; } }

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, int $status_code = 200): void {
        header('Content-Type: application/json', true, $status_code);
        echo json_encode(['success' => true, 'data' => $data]);
        // スタブなので exit はしない
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, int $status_code = 400): void {
        header('Content-Type: application/json', true, $status_code);
        echo json_encode(['success' => false, 'data' => $data]);
    }
}

// ------------------------------------------------------------
// HTTP スタブ
// ------------------------------------------------------------
if (!function_exists('wp_remote_get')) { function wp_remote_get($url, array $args = []) { return ['body' => '']; } }
if (!function_exists('wp_remote_post')){ function wp_remote_post($url, array $args = []) { return ['body' => '']; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($resp) { return is_array($resp) && isset($resp['body']) ? $resp['body'] : ''; } }

// ------------------------------------------------------------
// WP_Error
// ------------------------------------------------------------
if (!class_exists('WP_Error')) {
    class WP_Error {
        /** @var string */
        public $code;
        /** @var string */
        public $message;
        /** @var mixed */
        public $data;

        public function __construct(string $code = '', string $message = '', $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
}

// ------------------------------------------------------------
// REST サーバ定数
// ------------------------------------------------------------
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const READABLE   = 'GET';
        public const CREATABLE  = 'POST';
        public const EDITABLE   = 'PUT';
        public const DELETABLE  = 'DELETE';
        public const ALLMETHODS = ['GET','POST','PUT','PATCH','DELETE','OPTIONS'];
    }
}

// ------------------------------------------------------------
// WP_REST_Request（ArrayAccess 互換シグネチャで実装）
// ------------------------------------------------------------
if (!class_exists('WP_REST_Request')) {
    /**
     * 最低限のパラメータ格納と ArrayAccess の型互換を提供するスタブ。
     * Intelephense/PHPStan の互換性チェックをパスするため、
     * ArrayAccess メソッドのシグネチャは PHP8 以降の型を完全一致させている。
     */
    class WP_REST_Request implements \ArrayAccess {
        /** @var array<string,mixed> */
        private array $params = [];

        public function __construct(array $params = []) {
            $this->params = $params;
        }

        // ---- ArrayAccess ----
        /** @inheritDoc */
        public function offsetExists(mixed $offset): bool {
            $key = is_string($offset) ? $offset : (string)$offset;
            return array_key_exists($key, $this->params);
        }
        /** @inheritDoc */
        public function offsetGet(mixed $offset): mixed {
            $key = is_string($offset) ? $offset : (string)$offset;
            return $this->params[$key] ?? null;
        }
        /** @inheritDoc */
        public function offsetSet(mixed $offset, mixed $value): void {
            if ($offset === null) return; // 末尾追加は未サポート（REST用途では不要）
            $key = is_string($offset) ? $offset : (string)$offset;
            $this->params[$key] = $value;
        }
        /** @inheritDoc */
        public function offsetUnset(mixed $offset): void {
            $key = is_string($offset) ? $offset : (string)$offset;
            unset($this->params[$key]);
        }

        // ---- 便利メソッド（本物の WP に合わせた最小互換）----
        public function get_param(string $key): mixed {
            return $this->params[$key] ?? null;
        }
        /** @return array<string,mixed> */
        public function get_params(): array {
            return $this->params;
        }
        public function set_param(string $key, mixed $value): void {
            $this->params[$key] = $value;
        }
    }
}

// ------------------------------------------------------------
// WP_REST_Response とユーティリティ
// ------------------------------------------------------------
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        /** @var mixed */
        public $data;
        /** @var int */
        public $status;
        /** @var array<string,string> */
        public $headers;

        public function __construct(mixed $data = null, int $status = 200, array $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response(mixed $data): WP_REST_Response {
        return ($data instanceof WP_REST_Response) ? $data : new WP_REST_Response($data);
    }
}

// ------------------------------------------------------------
// REST ルート登録（引数4つの正式シグネチャ / 互換用）
// Intelephense の "Expected 4 arguments" を避けるため正しい宣言を定義。
// ------------------------------------------------------------
if (!function_exists('register_rest_route')) {
    /**
     * @param string                     $namespace
     * @param string                     $route
     * @param array<string,mixed>|array  $args
     * @param bool                       $override
     * @return bool
     */
    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool {
        // スタブ: 何もしない
        return true;
    }
}

// ------------------------------------------------------------
// ユーザー系の最小スタブ
// ------------------------------------------------------------
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object)[
            'ID' => 0,
            'user_email' => '',
            'user_login' => '',
            'display_name' => '',
        ];
    }
}
