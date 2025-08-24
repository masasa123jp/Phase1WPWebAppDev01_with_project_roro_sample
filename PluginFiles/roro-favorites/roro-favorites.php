<?php
/**
 * Plugin Name: RORO Favorites
 * Description: ユーザーのお気に入り（イベント/スポット）をDBに完全永続化し、重複登録を防止します。REST API・ショートコード・クエリパラメータ対応。
 * Version: 1.1.0
 * Author: Project RORO
 * Text Domain: roro-favorites
 * Domain Path: /lang
 *
 * 要件:
 *  - wp_RORO_MAP_FAVORITE（ユーザーx対象の重複防止ユニーク制約）を作成（存在しなければ）
 *  - REST: GET /roro/v1/favorites, POST /roro/v1/favorites/add, DELETE /roro/v1/favorites/remove
 *  - ショートコード: [roro_favorites]
 *  - クエリパラメータ: ?roro_fav_add=spot&spot_id=123 / ?roro_fav_remove=event&event_id=45
 *  - 多言語: ja / en / zh / ko
 */

if (!defined('ABSPATH')) { exit; }

define('RORO_FAV_VERSION', '1.1.0');
define('RORO_FAV_PATH', plugin_dir_path(__FILE__));
define('RORO_FAV_URL',  plugin_dir_url(__FILE__));

require_once RORO_FAV_PATH . 'includes/class-roro-favorites-service.php';
require_once RORO_FAV_PATH . 'includes/class-roro-favorites-rest.php';
require_once RORO_FAV_PATH . 'includes/class-roro-favorites-admin.php';

// 有効化: スキーマ作成
register_activation_hook(__FILE__, function () {
    $svc = new RORO_Favorites_Service();
    $svc->install_schema();
});

// 初期化: ショートコード、スクリプト、翻訳
add_action('init', function () {
    // ショートコード: [roro_favorites] お気に入り一覧表示
    add_shortcode('roro_favorites', function ($atts = []) {
        $svc = new RORO_Favorites_Service();
        $lang = $svc->detect_lang();
        $messages = $svc->load_lang($lang);
        if (!is_user_logged_in()) {
            // 未ログインの場合のメッセージを直接返す
            return '<p>' . esc_html($messages['must_login']) . '</p>';
        }

        // スクリプト登録とローカライズ
        wp_register_script(
            'roro-favorites-js',
            RORO_FAV_URL . 'assets/js/favorites.js',
            ['wp-api-fetch'],  // WP REST API fetch dependency
            RORO_FAV_VERSION,
            true
        );
        wp_localize_script('roro-favorites-js', 'roroFavorites', [
            'restBase' => esc_url_raw(rest_url('roro/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'lang'     => $lang,
            'i18n'     => $messages
        ]);
        wp_enqueue_script('roro-favorites-js');

        // テンプレートに渡すデータ準備
        $data = [
            'lang'     => $lang,
            'messages' => $messages
        ];
        ob_start();
        include RORO_FAV_PATH . 'templates/favorites-list.php';
        return ob_get_clean();
    });

    // ショートコード: [roro_favorite_button] (お気に入り追加/削除ボタン)
    add_shortcode('roro_favorite_button', function ($atts) {
        $atts = shortcode_atts([
            'type'  => '',
            'id'    => '',
            'title' => '',
            'url'   => '',
        ], $atts, 'roro_favorite_button');

        $svc = new RORO_Favorites_Service();
        $lang = $svc->detect_lang();
        $messages = $svc->load_lang($lang);
        if (!is_user_logged_in()) {
            // 未ログイン時はログインを促す表示
            return '<div class="roro-fav-hint">' . esc_html($messages['must_login']) . '</div>';
        }

        $type = sanitize_text_field($atts['type']);
        $id   = intval($atts['id']);
        if (!$type || !$id) return '';  // 種別またはIDが指定されていない場合は何も出力しない

        // 対象がお気に入り済みかチェック
        $exists = $svc->is_favorite(get_current_user_id(), $type, $id);
        $action = $exists ? 'remove' : 'add';

        // クエリパラメータ設定
        $params = [];
        if ($action === 'add') {
            $params['roro_fav_add'] = $type;
        } else {
            $params['roro_fav_remove'] = $type;
        }
        if ($type === 'spot') {
            $params['spot_id'] = $id;
        } elseif ($type === 'event') {
            $params['event_id'] = $id;
        } else {
            // 未知のタイプ: とりあえず共通キーに設定
            $params['target_id'] = $id;
        }
        // セキュリティ用Nonceを生成
        $params['_wpnonce'] = wp_create_nonce('roro_fav_' . $action . '_' . $type . '_' . $id);

        $url = esc_url(add_query_arg($params));
        $label = $exists ? $messages['btn_remove'] : $messages['btn_add'];
        return '<a href="' . $url . '" class="roro-fav-link">' . esc_html($label) . '</a>';
    });
});

// REST APIエンドポイント登録
add_action('rest_api_init', function () {
    (new RORO_Favorites_REST())->register_routes();
});

// 管理画面メニューの追加
add_action('admin_menu', function () {
    (new RORO_Favorites_Admin())->register_menu();
});

// クエリパラメータでの追加/削除（後方互換処理）
add_action('template_redirect', function () {
    // 未ログイン時は処理しない
    if (!is_user_logged_in()) return;

    $svc = new RORO_Favorites_Service();
    $lang = $svc->detect_lang();
    $messages = $svc->load_lang($lang);
    $redirect = remove_query_arg([ 'roro_fav_add', 'roro_fav_remove', 'spot_id', 'event_id', 'roro_fav_status' ]);

    // 追加処理
    if (isset($_GET['roro_fav_add'])) {
        $type = sanitize_text_field($_GET['roro_fav_add']);
        $id   = 0;
        if ($type === 'spot' && isset($_GET['spot_id'])) {
            $id = intval($_GET['spot_id']);
        } elseif ($type === 'event' && isset($_GET['event_id'])) {
            $id = intval($_GET['event_id']);
        }
        if ($id > 0) {
            // Nonceチェック
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'roro_fav_add_' . $type . '_' . $id)) {
                $redirect = add_query_arg('roro_fav_status', 'error', $redirect);
                wp_safe_redirect($redirect);
                exit;
            }
            $r = $svc->add_favorite(get_current_user_id(), $type, $id);
            if (is_wp_error($r)) {
                $redirect = add_query_arg('roro_fav_status', 'error', $redirect);
            } else {
                $redirect = add_query_arg('roro_fav_status', ($r === 'duplicate' ? 'duplicate' : 'added'), $redirect);
            }
            wp_safe_redirect($redirect);
            exit;
        }
    }

    // 削除処理
    if (isset($_GET['roro_fav_remove'])) {
        $type = sanitize_text_field($_GET['roro_fav_remove']);
        $id   = 0;
        if ($type === 'spot' && isset($_GET['spot_id'])) {
            $id = intval($_GET['spot_id']);
        } elseif ($type === 'event' && isset($_GET['event_id'])) {
            $id = intval($_GET['event_id']);
        }
        if ($id > 0) {
            // Nonceチェック
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'roro_fav_remove_' . $type . '_' . $id)) {
                $redirect = add_query_arg('roro_fav_status', 'error', $redirect);
                wp_safe_redirect($redirect);
                exit;
            }
            $r = $svc->remove_favorite(get_current_user_id(), $type, $id);
            $redirect = add_query_arg('roro_fav_status', (is_wp_error($r) ? 'error' : 'removed'), $redirect);
            wp_safe_redirect($redirect);
            exit;
        }
    }
});
