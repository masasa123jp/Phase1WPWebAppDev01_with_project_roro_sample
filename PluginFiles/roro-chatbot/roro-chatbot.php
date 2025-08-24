<?php
/**
 * Plugin Name: RORO Chatbot Endpoint
 * Description: roro-chatbot.js と連携する WordPress AJAX/REST エンドポイントを提供。
 * Version: 1.1.0
 * Author: RORO Dev Team
 * Text Domain: roro-chatbot
 */

if (!defined('ABSPATH')) exit;

// --- スクリプト登録（必要に応じてテーマ/他プラグインでenqueueしてください） ---------------------
add_action('wp_enqueue_scripts', function () {
  $handle = 'roro-chatbot';
  $src    = plugins_url('assets/js/roro-chatbot.js', __FILE__);
  wp_register_script($handle, $src, array(), '1.1.0', true);

  // 画面側で window.RORO_CHATBOT_BOOT に上書きできるよう最小限のみ付与
  wp_localize_script($handle, 'RORO_CHATBOT_BOOT', array(
    'id'       => 'roro-chatbot',
    'ajaxUrl'  => admin_url('admin-ajax.php'),
    'restUrl'  => esc_url_raw(rest_url('roro/v1/chat')),
    'nonce'    => wp_create_nonce('roro_chatbot'),
    'messages' => array(
      'bot'     => 'Bot',
      'you'     => 'You',
      'loading' => '…',
      'error'   => 'Failed.'
    )
  ));
});

// --- AJAX: roro_chatbot_send ---------------------------------------------------------------
add_action('wp_ajax_roro_chatbot_send',        'roro_chatbot_ajax_handler');
add_action('wp_ajax_nopriv_roro_chatbot_send', 'roro_chatbot_ajax_handler');

function roro_chatbot_ajax_handler() {
  $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'roro_chatbot')) {
    wp_send_json_error(array('message' => 'Invalid nonce'), 403);
  }
  $text = isset($_POST['text']) ? wp_strip_all_tags(wp_unslash($_POST['text'])) : '';
  if ($text === '') {
    wp_send_json_error(array('message' => 'Empty text'), 400);
  }

  // 外部実装へ委譲可能： 'roro_chatbot_generate_reply' フィルタ
  $context = array(
    'user_id'   => get_current_user_id(),
    'site_name' => get_bloginfo('name'),
    'referer'   => wp_get_referer(),
  );
  $reply = apply_filters('roro_chatbot_generate_reply', null, $text, $context);

  if ($reply === null) {
    // デフォルト：エコー + 例示（本番はAI実装に差し替え）
    $reply = '（デモ応答）' . $text;
  }

  wp_send_json_success(array('reply' => wp_kses_post($reply)));
}

// --- REST: /wp-json/roro/v1/chat  ----------------------------------------------------------
add_action('rest_api_init', function () {
  register_rest_route('roro/v1', '/chat', array(
    'methods'  => 'POST',
    'callback' => function (WP_REST_Request $req) {
      $nonce = $req->get_param('nonce');
      if (!wp_verify_nonce($nonce, 'roro_chatbot')) {
        return new WP_Error('forbidden', 'Invalid nonce', array('status' => 403));
      }
      $text = wp_strip_all_tags($req->get_param('text') ?: '');
      if ($text === '') return new WP_Error('bad_request', 'Empty text', array('status' => 400));

      $context = array(
        'user_id'   => get_current_user_id(),
        'site_name' => get_bloginfo('name'),
        'referer'   => $req->get_header('referer'),
      );
      $reply = apply_filters('roro_chatbot_generate_reply', null, $text, $context);
      if ($reply === null) $reply = '（デモ応答）' . $text;

      return rest_ensure_response(array('reply' => wp_kses_post($reply)));
    },
    'permission_callback' => '__return_true',
  ));
});
