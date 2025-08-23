<?php
/**
 * IDE-only WordPress function stubs for Intelephense.
 * 実行時には読み込まないこと（プラグイン本体から require/include しない）。
 * ワークスペースに存在するだけで Intelephense の索引に乗る。
 */

if (!function_exists('plugin_dir_url')) {
    /** @return string */
    function plugin_dir_url($file) { return ''; }
}
if (!function_exists('plugin_dir_path')) {
    /** @return string */
    function plugin_dir_path($file) { return ''; }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {}
}
if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback) {}
}

if (!function_exists('get_option')) {
    /** @return mixed */
    function get_option($option, $default = false) { return $default; }
}
if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes') { return true; }
}
if (!function_exists('delete_option')) {
    function delete_option($option) { return true; }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $func) {}
}

if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') { return true; }
}
if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) { return true; }
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {}
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {}
}
if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) { return true; }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null) { return (string)$url; }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest') { return (string)$path; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'nonce'; }
}
if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null) { return (string)$path; }
}
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() { return false; }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() { return 0; }
}
if (!function_exists('is_page')) {
    function is_page($page = null) { return false; }
}
