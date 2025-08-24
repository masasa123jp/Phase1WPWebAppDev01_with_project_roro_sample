<?php
/**
 * Plugin Name: RORO Magazine
 * Description: 月刊マガジンをカスタム投稿タイプで管理し、多言語（ja/en/zh/ko）とショートコードで公開します。
 * Version: 1.0.0
 * Author: Project RORO
 * Text Domain: roro-magazine
 * Domain Path: /lang
 *
 * ショートコード:
 *  - [roro_magazine]        : 号の一覧（?mag_issue={ID} があれば号ビューに切替）
 *  - [roro_mag_issue issue="ID|slug|YYYY-MM|latest"]
 *  - [roro_mag_article id="POST_ID"]
 *
 * REST:
 *  - GET /roro/v1/mag/issues?limit=6&offset=0
 *  - GET /roro/v1/mag/issue/(?P<id>\d+)/articles
 */
if (!defined('ABSPATH')) { exit; }

define('RORO_MAG_VERSION', '1.0.0');
define('RORO_MAG_PATH', plugin_dir_path(__FILE__));
define('RORO_MAG_URL',  plugin_dir_url(__FILE__));

require_once RORO_MAG_PATH . 'includes/class-roro-mag-service.php';
require_once RORO_MAG_PATH . 'includes/class-roro-mag-admin.php';
require_once RORO_MAG_PATH . 'includes/class-roro-mag-rest.php';

register_activation_hook(__FILE__, function(){
    // CPT登録 → リライトルール更新
    (new RORO_Mag_Service())->register_cpts();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

// 初期化: CPT, 翻訳, アセット
add_action('init', function(){
    (new RORO_Mag_Service())->register_cpts();
});

add_action('plugins_loaded', function(){
    load_plugin_textdomain('roro-magazine', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

add_action('wp_enqueue_scripts', function(){
    wp_register_style('roro-magazine', RORO_MAG_URL . 'assets/css/magazine.css', [], RORO_MAG_VERSION);
    wp_register_script('roro-magazine', RORO_MAG_URL . 'assets/js/magazine.js', [], RORO_MAG_VERSION, true);
});

// 管理画面
add_action('add_meta_boxes', function(){
    (new RORO_Mag_Admin())->register_meta_boxes();
});
add_action('save_post', function($post_id){
    (new RORO_Mag_Admin())->save_meta_boxes($post_id);
});
add_filter('manage_roro_mag_article_posts_columns', function($cols){
    return (new RORO_Mag_Admin())->columns_articles($cols);
});
add_action('manage_roro_mag_article_posts_custom_column', function($col, $post_id){
    (new RORO_Mag_Admin())->columns_articles_content($col, $post_id);
}, 10, 2);

// REST
add_action('rest_api_init', function(){
    (new RORO_Mag_REST())->register_routes();
});

/**
 * ショートコード: [roro_magazine]
 * - 号一覧表示。?mag_issue=ID があれば号詳細を表示。
 */
add_shortcode('roro_magazine', function($atts){
    $svc = new RORO_Mag_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);

    wp_enqueue_style('roro-magazine');
    wp_enqueue_script('roro-magazine');

    $issueId = isset($_GET['mag_issue']) ? intval($_GET['mag_issue']) : 0;
    $data = compact('lang', 'M', 'issueId');
    ob_start();
    include RORO_MAG_PATH . 'templates/magazine-list.php';
    return ob_get_clean();
});

/**
 * ショートコード: [roro_mag_issue issue="ID|slug|YYYY-MM|latest"]
 */
add_shortcode('roro_mag_issue', function($atts){
    $atts = shortcode_atts([ 'issue' => 'latest' ], $atts, 'roro_mag_issue');
    $svc = new RORO_Mag_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);
    $issue_id = $svc->resolve_issue_id($atts['issue']);
    if (!$issue_id) return '<div class="roro-mag-empty">'.esc_html($M['no_issues']).'</div>';

    wp_enqueue_style('roro-magazine');
    wp_enqueue_script('roro-magazine');

    $data = [
        'lang' => $lang,
        'M'    => $M,
        'issue_id' => $issue_id
    ];
    ob_start();
    include RORO_MAG_PATH . 'templates/issue-view.php';
    return ob_get_clean();
});

/**
 * ショートコード: [roro_mag_article id="POST_ID"]
 */
add_shortcode('roro_mag_article', function($atts){
    $atts = shortcode_atts([ 'id' => 0 ], $atts, 'roro_mag_article');
    $svc = new RORO_Mag_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);
    $post = get_post(intval($atts['id']));
    if (!$post || $post->post_type !== 'roro_mag_article') {
        return '<div class="roro-mag-empty">'.esc_html($M['no_articles']).'</div>';
    }
    wp_enqueue_style('roro-magazine');
    $data = [
        'lang' => $lang,
        'M'    => $M,
        'article' => $svc->article_payload($post, $lang)
    ];
    ob_start();
    include RORO_MAG_PATH . 'templates/article-view.php';
    return ob_get_clean();
});
