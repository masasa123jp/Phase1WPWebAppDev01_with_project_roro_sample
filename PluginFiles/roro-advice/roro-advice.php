<?php
/**
 * Plugin Name: RORO Advice
 * Description: 簡易レコメンド（ワンポイントアドバイス）を表示。ショートコード [roro_advice_random]（属性：category="..."）
 * Version: 1.1.0
 * Author: Project RORO
 * Text Domain: roro-advice
 * Domain Path: /lang
 */
if (!defined('ABSPATH')) { exit; }

define('RORO_ADV_VERSION', '1.1.0');
define('RORO_ADV_PATH', plugin_dir_path(__FILE__));
define('RORO_ADV_URL',  plugin_dir_url(__FILE__));

require_once RORO_ADV_PATH . 'includes/class-roro-advice-service.php';
require_once RORO_ADV_PATH . 'includes/class-roro-advice-rest.php';

add_action('plugins_loaded', function(){
    load_plugin_textdomain('roro-advice', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

add_shortcode('roro_advice_random', function($atts){
    $atts = shortcode_atts(['category'=>''], $atts, 'roro_advice_random');
    $svc  = new RORO_Advice_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);
    $ad   = $svc->get_random_advice($atts['category']);
    ob_start();
    ?>
    <div class="roro-adv">
      <div class="roro-adv-title"><?php echo esc_html($M['advice']); ?></div>
      <?php if(!$ad): ?>
        <div class="roro-adv-empty"><?php echo esc_html($M['no_advice']); ?></div>
      <?php else: ?>
        <div class="roro-adv-body"><?php echo esc_html($ad); ?></div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

add_action('rest_api_init', function(){ (new RORO_Advice_REST())->register_routes(); });
