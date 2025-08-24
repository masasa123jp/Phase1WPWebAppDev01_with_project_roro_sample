<?php
/**
 * DEV ONLY STUBS for IDE static analysis (Intelephense).
 * 本番では require しないこと。
 *
 * - WordPress コア関数 / クラスの最低限のシグネチャを宣言し、IDE の未定義エラーを防ぎます。
 * - 実行時には読み込まれないように、このファイルをビルド/デプロイから除外してください。
 */

declare(strict_types=1);

// ---- Common constants used by WP Core ----
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT'))  define('OBJECT', 'OBJECT');

// ---- WP REST types ----
if (!class_exists('WP_REST_Request'))  {
    class WP_REST_Request implements ArrayAccess {
        public function offsetSet($k,$v){} public function offsetExists($k){return false;}
        public function offsetUnset($k){} public function offsetGet($k){return null;}
        public function get_param($k){return null;} public function get_params(){return [];}
        public function get_json_params(){return [];} public function get_body_params(){return [];}
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct($data = null, int $status = 200, array $headers = []) {}
    }
}

// ---- Query stub ----
if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        public $max_num_pages = 1;
        public function __construct($args = []) {}
        public function have_posts(): bool { return false; }
        public function the_post(): void {}
        public function get_posts(): array { return []; }
    }
}
// ---- wpdb stub ----
if (!class_exists('wpdb')) {
    class wpdb {
        public function query($query) { return 0; }
        public function prepare($query, ...$args) { return $query; }
        public function get_results($query, $output = OBJECT) { return []; }
        public function get_var($query, $x = 0, $y = 0) { return null; }
    }
    if (!isset($GLOBALS['wpdb'])) { $GLOBALS['wpdb'] = new wpdb(); }
}

// ---- Post object stub ----
if (!class_exists('WP_Post')) {
    class WP_Post { public $ID; public $post_title; public $post_excerpt; public $post_content; }
}

// ---- Declare commonly used functions with correct signatures ----
if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {}
}
if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {}
}
if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, $callback): void {}
}

// Activation / Deactivation / Uninstall
if (!function_exists('register_activation_hook')) { function register_activation_hook(string $file, $callback): void {} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(string $file, $callback): void {} }
if (!function_exists('register_uninstall_hook')) { function register_uninstall_hook(string $file, $callback): void {} }

// Admin menu / Settings API
if (!function_exists('add_menu_page')) { function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, $function = '', string $icon_url = '', $position = null) {} }
if (!function_exists('add_submenu_page')) { function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $function = '') {} }
if (!function_exists('register_setting')) { function register_setting(string $option_group, string $option_name, $args = []): void {} }
if (!function_exists('add_settings_section')) { function add_settings_section(string $id, string $title, $callback, string $page): void {} }
if (!function_exists('add_settings_field')) { function add_settings_field(string $id, string $title, $callback, string $page, string $section = 'default', $args = []): void {} }
if (!function_exists('settings_fields')) { function settings_fields(string $option_group): void {} }
if (!function_exists('do_settings_sections')) { function do_settings_sections(string $page): void {} }
if (!function_exists('submit_button')) { function submit_button($text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void {} }
if (!function_exists('get_option')) { function get_option(string $option, $default = false) {} }
if (!function_exists('update_option')) { function update_option(string $option, $value, $autoload = null) {} }
if (!function_exists('add_option')) { function add_option(string $option, $value = '', string $deprecated = '', string $autoload = 'yes') {} }
if (!function_exists('delete_option')) { function delete_option(string $option): bool { return true; } }

// URLs / Nonces
if (!function_exists('plugin_dir_path')) { function plugin_dir_path(string $file): string { return ''; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url(string $file): string { return ''; } }
if (!function_exists('home_url')) { function home_url(string $path = '', $scheme = null): string { return ''; } }
if (!function_exists('admin_url')) { function admin_url(string $path = '', string $scheme = 'admin'): string { return ''; } }
if (!function_exists('rest_url')) { function rest_url(string $path = ''): string { return ''; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, string $url = ''): string { return ''; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1): string { return ''; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action = -1): int|false { return 1; } }
if (!function_exists('check_admin_referer')) { function check_admin_referer($action = -1, $query_arg = '_wpnonce'): bool { return true; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field($action = -1, $name = '_wpnonce', bool $referer = true, bool $echo = true, bool $use_referrer = true) {} }

// Enqueue / Localize
if (!function_exists('wp_register_style')) { function wp_register_style(string $handle, string $src, array $deps = [], $ver = false, string $media = 'all'): void {} }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void {} }
if (!function_exists('wp_register_script')) { function wp_register_script(string $handle, string $src, array $deps = [], $ver = false, bool $in_footer = false): void {} }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void {} }
if (!function_exists('wp_localize_script')) { function wp_localize_script(string $handle, string $object_name, array $l10n): bool { return true; } }

// Sanitization / Escaping / I18n
if (!function_exists('sanitize_text_field')) { function sanitize_text_field(string $str): string { return $str; } }
if (!function_exists('sanitize_email')) { function sanitize_email(string $email): string { return $email; } }
if (!function_exists('sanitize_user')) { function sanitize_user(string $username, bool $strict = false): string { return $username; } }
if (!function_exists('is_email')) { function is_email(string $email, bool $deprecated = false) { return $email; } }
if (!function_exists('email_exists')) { function email_exists(string $email) { return false; } }
if (!function_exists('username_exists')) { function username_exists(string $username) { return false; } }
if (!function_exists('esc_attr')) { function esc_attr($text): string { return (string)$text; } }
if (!function_exists('esc_html')) { function esc_html($text): string { return (string)$text; } }
if (!function_exists('esc_html__')) { function esc_html__($text, string $domain = 'default'): string { return (string)$text; } }
if (!function_exists('esc_html_e')) { function esc_html_e($text, string $domain = 'default'): void {} }
if (!function_exists('__')) { function __($text, string $domain = 'default'): string { return (string)$text; } }
if (!function_exists('_e')) { function _e($text, string $domain = 'default'): void {} }
if (!function_exists('_x')) { function _x($text, string $context, string $domain = 'default'): string { return (string)$text; } }
if (!function_exists('checked')) { function checked($checked, $current = true, bool $echo = true) { return $checked === $current ? ' checked="checked"' : ''; } }

// REST API
if (!function_exists('register_rest_route')) { function register_rest_route(string $route_namespace, string $route, array $args = [], bool $override = false): bool { return true; } }
if (!function_exists('rest_ensure_response')) { function rest_ensure_response($response) { return $response; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data, int $options = 0, int $depth = 512): string { return json_encode($data); } }

// Post / Meta / Taxonomy
if (!function_exists('register_post_type')) { function register_post_type(string $post_type, array $args = []): void {} }
if (!function_exists('register_taxonomy')) { function register_taxonomy(string $taxonomy, $object_type, array $args = []): void {} }
if (!function_exists('wp_insert_post')) { function wp_insert_post(array $postarr = [], $wp_error = false) { return 0; } }
if (!function_exists('wp_update_post')) { function wp_update_post(array $postarr = [], bool $wp_error = false) { return 0; } }
if (!function_exists('get_post')) { function get_post($post_id = null) { return null; } }
if (!function_exists('get_posts')) { function get_posts(array $args = []): array { return []; } }
if (!function_exists('get_post_meta')) { function get_post_meta(int $post_id, string $key = '', bool $single = false) { return null; } }
if (!function_exists('update_post_meta')) { function update_post_meta(int $post_id, string $meta_key, $meta_value, $prev_value = '') { return true; } }
if (!function_exists('delete_post_meta')) { function delete_post_meta(int $post_id, string $meta_key, $meta_value = '') { return true; } }
if (!function_exists('wp_set_object_terms')) { function wp_set_object_terms($object_id, $terms, string $taxonomy, bool $append = false) { return []; } }

if (!function_exists('get_the_permalink')) { function get_the_permalink($post = null): string { return ''; } }
if (!function_exists('get_permalink')) { function get_permalink($post = null): string { return ''; } }
if (!function_exists('get_the_title')) { function get_the_title($post = 0): string { return ''; } }
if (!function_exists('get_the_excerpt')) { function get_the_excerpt($post = null): string { return ''; } }
if (!function_exists('get_the_post_thumbnail_url')) { function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail'): string { return ''; } }

// User
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(): bool { return false; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(): int { return 0; } }
if (!function_exists('get_user_by')) { function get_user_by(string $field, $value) { return null; } }
if (!function_exists('get_userdata')) { function get_userdata(int $user_id) { return null; } }
if (!function_exists('wp_signon')) { function wp_signon(array $credentials = [], $secure_cookie = '') { return null; } }
if (!function_exists('wp_logout')) { function wp_logout(): void {} }
if (!function_exists('wp_set_current_user')) { function wp_set_current_user(int $user_id) {} }
if (!function_exists('wp_set_auth_cookie')) { function wp_set_auth_cookie(int $user_id, bool $remember = false, $secure = '', $token = '') {} }
if (!function_exists('update_user_meta')) { function update_user_meta(int $user_id, string $meta_key, $meta_value, $prev_value = '') { return true; } }
if (!function_exists('get_user_meta')) { function get_user_meta(int $user_id, string $key = '', bool $single = false) { return null; } }

// Misc
if (!function_exists('current_time')) { function current_time(string $type, $gmt = 0) { return time(); } }
if (!function_exists('wp_die')) { function wp_die($message = '', $title = '', array $args = []) {} }
