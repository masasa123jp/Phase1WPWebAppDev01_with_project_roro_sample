<?php
/**
 * Plugin Name: Roro Map
 * Description: 自宅位置のGUI保存（RESTフロント層）・クラスタリング/距離ソート支援JSを同梱。
 * Version: 1.6.0
 * Requires at least: 6.3
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Text Domain: roro-map
 */
if (!defined('ABSPATH')) { exit; }

// ショートコード: [roro_home_location]
add_shortcode('roro_home_location', function($atts){
    if (!is_user_logged_in()) { return '<p>ログインしてください。</p>'; }
    wp_enqueue_script('roro-home-location');
    $nonce = wp_create_nonce('wp_rest');
    $rest  = esc_url_raw( rest_url('roro/v1/me/home') );
    ob_start(); ?>
    <div id="roro-home-ui" data-rest="<?php echo esc_attr($rest); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
      <p>現在地を取得して保存するか、緯度経度を手動入力して保存できます。</p>
      <div style="display:flex;gap:8px;">
        <input id="roro-lat" type="number" step="0.000001" placeholder="緯度"/>
        <input id="roro-lng" type="number" step="0.000001" placeholder="経度"/>
      </div>
      <p style="margin-top:8px;">
        <button id="roro-use-geo" class="button">現在地を使用</button>
        <button id="roro-save-home" class="button button-primary">保存</button>
        <span id="roro-home-msg"></span>
      </p>
    </div>
    <?php return ob_get_clean();
});

// アセット登録
add_action('wp_enqueue_scripts', function(){
    wp_register_script('roro-home-location', plugins_url('assets/js/home-location.js', __FILE__), [], '1.0.0', true);
    wp_register_script('roro-cluster-sort', plugins_url('assets/js/cluster-sort.js', __FILE__), [], '1.0.0', true);
});
