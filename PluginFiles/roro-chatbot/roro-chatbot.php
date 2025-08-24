<?php
/**
 * Plugin Name: RORO Chatbot
 * Description: ショートコード [roro_chatbot] を提供。外部AI（OpenAI/Dify）またはフォールバックの簡易応答を選択可能。会話は任意でDB（RORO_AI_CONVERSATION/MESSAGE）へ保存。
 * Version: 1.2.0
 * Author: Project RORO
 * Text Domain: roro-chatbot
 * Domain Path: /lang
 */
if (!defined('ABSPATH')) { exit; }

define('RORO_CHAT_VERSION', '1.2.0');
define('RORO_CHAT_PATH', plugin_dir_path(__FILE__));
define('RORO_CHAT_URL',  plugin_dir_url(__FILE__));

require_once RORO_CHAT_PATH . 'includes/class-roro-chat-service.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-rest.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-admin.php';

add_action('plugins_loaded', function(){
    load_plugin_textdomain('roro-chatbot', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

// フロント用アセット
add_action('wp_enqueue_scripts', function(){
    wp_register_style('roro-chat-css', RORO_CHAT_URL . 'assets/css/roro-chat.css', [], RORO_CHAT_VERSION);
    wp_register_script('roro-chat-js', RORO_CHAT_URL . 'assets/js/roro-chat.js', [], RORO_CHAT_VERSION, true);
});

// ショートコード
add_shortcode('roro_chatbot', function($atts){
    $svc  = new RORO_Chat_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);

    wp_enqueue_style('roro-chat-css');
    wp_enqueue_script('roro-chat-js');

    wp_localize_script('roro-chat-js', 'RORO_CHAT_CFG', [
        'restBase' => esc_url_raw( rest_url('roro/v1') ),
        'nonce'    => wp_create_nonce('wp_rest'),
        'i18n'     => $M,
        'settings' => [
            'provider'  => get_option('roro_chat_provider', 'echo'),
            'model'     => get_option('roro_chat_openai_model', 'gpt-4o-mini'),
        ]
    ]);

    ob_start();
    ?>
    <div class="roro-chat-wrap">
      <div class="roro-chat-header">
        <div class="roro-chat-title"><?php echo esc_html($M['chat_title']); ?></div>
        <div class="roro-chat-provider"><?php echo esc_html($M['provider']); ?>: <?php echo esc_html( strtoupper(get_option('roro_chat_provider', 'echo')) ); ?></div>
      </div>
      <div id="roro-chat-log" class="roro-chat-log" aria-live="polite"></div>
      <div class="roro-chat-input">
        <input type="text" id="roro-chat-text" placeholder="<?php echo esc_attr($M['placeholder']); ?>" />
        <button type="button" id="roro-chat-send"><?php echo esc_html($M['send']); ?></button>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

// REST登録
add_action('rest_api_init', function(){ (new RORO_Chat_REST())->register_routes(); });

// 設定画面
add_action('admin_menu', function(){
    add_options_page('RORO Chatbot', 'RORO Chatbot', 'manage_options', 'roro-chatbot', function(){
        (new RORO_Chat_Admin())->render_settings_page();
    });
});
