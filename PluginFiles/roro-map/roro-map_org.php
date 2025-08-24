<?php
/**
 * Plugin Name: RORO Map
 * Description: RORO用 Google Maps 表示プラグイン。ショートコード [roro_map] で表示。マーカーはJSONで指定可能。多言語対応（内蔵辞書）。
 * Version: 1.0.0
 * Author: Project RORO Team
 */

if (!defined('ABSPATH')) exit;

/* ==========================================================
 * 定数
 * ========================================================== */
define('RORO_MAP_VERSION', '1.0.0');
define('RORO_MAP_DIR', plugin_dir_path(__FILE__));
define('RORO_MAP_URL', plugin_dir_url(__FILE__));
define('RORO_MAP_OPTION_API_KEY', 'roro_map_google_api_key');

/* ==========================================================
 * 多言語（簡易辞書）
 * 必要最小限のキーのみ。必要に応じて追加してください。
 * ========================================================== */
function roro_map_messages() {
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $lang = substr($locale, 0, 2);
    $dict = array(
        'en' => array(
            'map'           => 'Map',
            'no_markers'    => 'No markers provided.',
            'error_loading' => 'Failed to load map data.',
            'more'          => 'More',
        ),
        'ja' => array(
            'map'           => '地図',
            'no_markers'    => 'マーカーが指定されていません。',
            'error_loading' => '地図データの読み込みに失敗しました。',
            'more'          => '詳しく',
        ),
        'zh' => array(
            'map'           => '地图',
            'no_markers'    => '未提供标记点。',
            'error_loading' => '地图数据加载失败。',
            'more'          => '更多',
        ),
        'ko' => array(
            'map'           => '지도',
            'no_markers'    => '마커가 제공되지 않았습니다.',
            'error_loading' => '지도 데이터를 불러오지 못했습니다.',
            'more'          => '자세히',
        ),
    );
    return $dict[$lang] ?? $dict['en'];
}

/* ==========================================================
 * アセット
 * ========================================================== */
add_action('wp_enqueue_scripts', function () {
    wp_register_script(
        'roro-map-js',
        RORO_MAP_URL . 'assets/js/roro-map.js',
        array(),
        RORO_MAP_VERSION,
        true
    );
});

/* ==========================================================
 * 管理画面（APIキー設定）
 * ========================================================== */
add_action('admin_menu', function () {
    add_options_page(
        'RORO Map 設定',
        'RORO Map 設定',
        'manage_options',
        'roro-map-settings',
        'roro_map_render_settings_page'
    );
});

function roro_map_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $messages = roro_map_messages();

    if (isset($_POST['roro_map_save'])) {
        check_admin_referer('roro_map_settings');
        update_option(RORO_MAP_OPTION_API_KEY, sanitize_text_field($_POST['roro_map_api_key'] ?? ''));
        echo '<div class="updated"><p>保存しました。</p></div>';
    }

    $api_key = esc_attr(get_option(RORO_MAP_OPTION_API_KEY, ''));
    ?>
    <div class="wrap">
      <h1>RORO Map 設定</h1>
      <form method="post">
        <?php wp_nonce_field('roro_map_settings'); ?>
        <table class="form-table">
          <tr>
            <th><label for="roro_map_api_key">Google Maps API Key</label></th>
            <td><input type="text" id="roro_map_api_key" name="roro_map_api_key" class="regular-text" value="<?php echo $api_key; ?>"></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <p><strong>注意：</strong> 認証付きAPIキーを使用し、必要に応じてリファラ制限を設定してください。</p>
    </div>
    <?php
}

/* ==========================================================
 * ショートコード [roro_map]
 * 例）[roro_map lat="35.68" lng="139.76" zoom="12" height="420" markers='[{"lat":35.68,"lng":139.76,"title":"Hachiōji","url":"/place/1"}]']
 *       data-src でJSON URLを指定可能（同一オリジン推奨）
 * ========================================================== */
add_shortcode('roro_map', function ($atts) {
    $atts = shortcode_atts(array(
        'lat'    => '35.6809591',
        'lng'    => '139.7673068',
        'zoom'   => '12',
        'height' => '420',
        'markers'=> '',     // JSON文字列（[{lat,lng,title,desc,url}]）
        'src'    => '',     // 外部/内部JSONのURL（CORS/同一オリジン注意）
        'id'     => 'roro-map-' . wp_generate_password(6, false),
    ), $atts, 'roro_map');

    $messages = roro_map_messages();

    $api_key = get_option(RORO_MAP_OPTION_API_KEY, '');
    // 初回呼び出し時のみGoogle Maps JSを出力するため、フラグを用意
    static $printed_loader = false;

    // フロントJSへローカライズ
    wp_enqueue_script('roro-map-js');
    wp_localize_script('roro-map-js', 'RORO_MAP_BOOT', array(
        'apiKey'   => $api_key,
        'messages' => $messages,
    ));

    // HTML出力
    $container_id = esc_attr($atts['id']);
    $lat  = esc_attr($atts['lat']);
    $lng  = esc_attr($atts['lng']);
    $zoom = (int)$atts['zoom'];
    $height = (int)$atts['height'];
    $markers_json = trim($atts['markers']);
    $src = esc_url_raw($atts['src']);

    ob_start(); ?>
    <div
        id="<?php echo $container_id; ?>"
        class="roro-map-container"
        style="width:100%;height:<?php echo $height; ?>px;"
        data-lat="<?php echo $lat; ?>"
        data-lng="<?php echo $lng; ?>"
        data-zoom="<?php echo $zoom; ?>"
        data-markers="<?php echo esc_attr($markers_json); ?>"
        data-src="<?php echo esc_attr($src); ?>"
    ></div>
    <?php
    $html = ob_get_clean();

    // Google Mapsローダー（defer）を一度だけ出力
    if (!$printed_loader && !empty($api_key)) {
        add_action('wp_footer', function () use ($api_key) {
            printf(
                '<script src="https://maps.googleapis.com/maps/api/js?key=%s" async defer></script>',
                esc_attr($api_key)
            );
        }, 100);
        $printed_loader = true;
    }

    return $html;
});
