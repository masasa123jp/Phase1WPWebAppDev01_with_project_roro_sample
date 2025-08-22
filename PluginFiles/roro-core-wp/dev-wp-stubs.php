<?php
/**
 * DEV ONLY STUBS for IDE static analysis (Intelephense).
 * このファイルは IDE の補助用です。本番で require しないでください。
 */

if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT'))  define('OBJECT', 'OBJECT');

if (!class_exists('WP_REST_Request'))  {
    class WP_REST_Request implements ArrayAccess {
        public function offsetSet($k,$v){} public function offsetExists($k){return false;}
        public function offsetUnset($k){} public function offsetGet($k){return null;}
        public function get_param($k){return null;} public function get_params(){return [];}
        public function get_json_params(){return [];} public function get_body_params(){return [];}
        public function get_header($name){return null;}
    }
}
if (!class_exists('WP_REST_Response')) { class WP_REST_Response { public function __construct($d=null,$s=200,$h=[]){ } public function set_status($c){ return $this; } } }

foreach ([
    'add_action','add_filter','add_shortcode','add_menu_page',
    'register_activation_hook','register_deactivation_hook','register_uninstall_hook',
    'plugin_dir_path','plugin_dir_url','home_url','rest_url','wp_create_nonce','wp_verify_nonce',
    'wp_register_style','wp_register_script','wp_enqueue_style','wp_enqueue_script','wp_localize_script',
    'current_user_can','wp_die','get_option','update_option','add_option','delete_option',
    'check_admin_referer','wp_nonce_field','submit_button','esc_attr','esc_html','esc_url_raw','checked',
    'sanitize_text_field','sanitize_email','sanitize_user','is_email','email_exists','is_wp_error',
    'wp_signon','wp_logout','is_user_logged_in','get_current_user_id','get_user_by','get_userdata',
    'register_rest_route','wp_json_encode','rest_ensure_response','username_exists','wp_update_user',
    'current_time'
] as $fn) {
    if (!function_exists($fn)) { eval("function {$fn}(){ return null; }"); }
}
