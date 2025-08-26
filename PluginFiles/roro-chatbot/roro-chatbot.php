<?php
/**
 * Plugin Name: RORO Chatbot
 * Description: Adds a multilingual AI chat interface for pet-related Q&A. Includes a shortcode [roro_chatbot] to embed a chat window that can use OpenAI, Dify or a simple fallback when no external provider is configured. Conversation history is stored server‑side and a settings page allows administrators to configure providers and API keys.
 * Version: 2.0.0
 * Author: Project RORO
 * Text Domain: roro-chatbot
 * Domain Path: /lang
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('RORO_CHAT_VERSION', '2.0.0');
define('RORO_CHAT_PATH', plugin_dir_path(__FILE__));
define('RORO_CHAT_URL',  plugin_dir_url(__FILE__));

// Load class files
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-i18n.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-database.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-provider.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-provider-openai.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-provider-dify.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-fallback.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-events.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-service.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-rest.php';
require_once RORO_CHAT_PATH . 'includes/class-roro-chat-admin.php';

// Load translations for static strings defined via __()/_e()
add_action('plugins_loaded', function() {
    load_plugin_textdomain('roro-chatbot', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

// Register styles and scripts on the front end
add_action('wp_enqueue_scripts', function() {
    wp_register_style('roro-chatbot-style', RORO_CHAT_URL . 'assets/css/roro-chat.css', [], RORO_CHAT_VERSION);
    // Depend on jQuery for convenience. Use versioned handle.
    wp_register_script('roro-chatbot-js', RORO_CHAT_URL . 'assets/js/roro-chat.js', ['jquery'], RORO_CHAT_VERSION, true);
});

// Shortcode implementation: outputs chat widget markup and passes config via localized script
add_shortcode('roro_chatbot', function($atts) {
    // Detect and load the current language
    $lang     = RORO_Chat_I18n::detect_lang();
    $messages = RORO_Chat_I18n::load_messages($lang);
    // Enqueue front‑end assets
    wp_enqueue_style('roro-chatbot-style');
    wp_enqueue_script('roro-chatbot-js');
    // Localize configuration to the JS
    wp_localize_script('roro-chatbot-js', 'RORO_CHAT_CFG', [
        'restBase' => esc_url_raw(rest_url('roro/v1')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'i18n'     => $messages,
        'settings' => [
            'provider' => get_option('roro_chat_provider', 'echo'),
            'model'    => get_option('roro_chat_openai_model', 'gpt-4o-mini'),
        ],
    ]);
    // Build output markup; messages array provides translation for labels
    ob_start();
    ?>
    <div class="roro-chat-wrap">
      <div class="roro-chat-header">
        <div class="roro-chat-title"><?php echo esc_html($messages['chat_title'] ?? 'Chatbot'); ?></div>
        <div class="roro-chat-provider">
          <?php echo esc_html($messages['provider'] ?? 'Provider'); ?>:
          <?php echo esc_html(strtoupper(get_option('roro_chat_provider', 'echo'))); ?>
        </div>
      </div>
      <div id="roro-chat-log" class="roro-chat-log" aria-live="polite"></div>
      <div class="roro-chat-input">
        <input type="text" id="roro-chat-text" placeholder="<?php echo esc_attr($messages['placeholder'] ?? 'Type your question…'); ?>" />
        <button type="button" id="roro-chat-send"><?php echo esc_html($messages['send'] ?? 'Send'); ?></button>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

// Register REST API route
add_action('rest_api_init', function() {
    (new RORO_Chat_REST())->register_routes();
});

// Register settings page
add_action('admin_menu', function() {
    add_options_page(
        __('RORO Chatbot', 'roro-chatbot'),
        __('RORO Chatbot', 'roro-chatbot'),
        'manage_options',
        'roro-chatbot',
        function() {
            (new RORO_Chat_Admin())->render_settings_page();
        }
    );
});