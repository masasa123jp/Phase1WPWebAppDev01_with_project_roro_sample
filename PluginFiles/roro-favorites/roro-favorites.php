<?php
/**
 * Plugin Name: RORO Favorites
 * Description: ユーザー毎のお気に入りをユーザーメタに保存。追加/削除リンクと一覧ショートコードを提供。多言語UI。
 * Version: 1.0.0
 * Author: Project RORO Team
 */

if (!defined('ABSPATH')) exit;

define('RORO_FAV_VERSION', '1.0.0');

/* ==========================================================
 * 多言語辞書
 * ========================================================== */
function roro_fav_messages() {
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $lang = substr($locale, 0, 2);
    $dict = array(
        'en' => array(
            'must_login' => 'Please sign in to use favorites.',
            'added'      => 'Added to favorites.',
            'removed'    => 'Removed from favorites.',
            'list_title' => 'My Favorites',
            'add'        => 'Add to favorites',
            'remove'     => 'Remove',
        ),
        'ja' => array(
            'must_login' => 'お気に入り機能を利用するにはログインが必要です。',
            'added'      => 'お気に入りに追加しました。',
            'removed'    => 'お気に入りから削除しました。',
            'list_title' => 'お気に入り一覧',
            'add'        => 'お気に入りに追加',
            'remove'     => '削除',
        ),
        'zh' => array(
            'must_login' => '请先登录以使用收藏功能。',
            'added'      => '已加入收藏。',
            'removed'    => '已从收藏中移除。',
            'list_title' => '我的收藏',
            'add'        => '加入收藏',
            'remove'     => '移除',
        ),
        'ko' => array(
            'must_login' => '즐겨찾기 기능을 사용하려면 로그인하세요.',
            'added'      => '즐겨찾기에 추가했습니다.',
            'removed'    => '즐겨찾기에서 삭제했습니다.',
            'list_title' => '즐겨찾기 목록',
            'add'        => '즐겨찾기에 추가',
            'remove'     => '삭제',
        ),
    );
    return $dict[$lang] ?? $dict['en'];
}

/* ==========================================================
 * 低依存の保存形式：user_meta に配列 [ [type,id,title,url], ... ]
 * ========================================================== */
function roro_fav_key() { return '_roro_favorites'; }

function roro_fav_get_list($user_id = 0) {
    $user_id = $user_id ?: get_current_user_id();
    $list = get_user_meta($user_id, roro_fav_key(), true);
    return is_array($list) ? $list : array();
}
function roro_fav_save_list($list, $user_id = 0) {
    $user_id = $user_id ?: get_current_user_id();
    update_user_meta($user_id, roro_fav_key(), array_values($list));
}
function roro_fav_find_index($list, $type, $id) {
    foreach ($list as $i => $row) {
        if (($row['type'] ?? '') === $type && (string)($row['id'] ?? '') === (string)$id) return $i;
    }
    return -1;
}

/* ==========================================================
 * 追加/削除ハンドル（GETリンク）
 * 例）?roro_fav_action=add&type=event&id=123&title=Foo&url=/event/123&_wpnonce=XXXX
 * ========================================================== */
add_action('init', function(){
    if (!isset($_GET['roro_fav_action'])) return;

    $msgs = roro_fav_messages();
    if (!is_user_logged_in()) {
        if (!is_admin()) wp_die(esc_html($msgs['must_login']));
        return;
    }

    $action = sanitize_text_field($_GET['roro_fav_action']);
    $type   = sanitize_text_field($_GET['type'] ?? '');
    $id     = sanitize_text_field($_GET['id']   ?? '');

    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'roro_fav_'.$action.'_'.$type.'_'.$id)) {
        return; // 不正は静かに無視
    }

    $list = roro_fav_get_list();
    if ($action === 'add') {
        $title = sanitize_text_field($_GET['title'] ?? '');
        $url   = esc_url_raw($_GET['url'] ?? '');
        if ($type && $id) {
            if (roro_fav_find_index($list, $type, $id) === -1) {
                $list[] = array('type'=>$type, 'id'=>$id, 'title'=>$title, 'url'=>$url);
                roro_fav_save_list($list);
                if (!is_admin()) wp_safe_redirect(remove_query_arg(array('roro_fav_action','type','id','title','url','_wpnonce')));
                exit;
            }
        }
    } elseif ($action === 'remove') {
        $idx = roro_fav_find_index($list, $type, $id);
        if ($idx >= 0) {
            unset($list[$idx]);
            roro_fav_save_list($list);
            if (!is_admin()) wp_safe_redirect(remove_query_arg(array('roro_fav_action','type','id','_wpnonce')));
            exit;
        }
    }
});

/* ==========================================================
 * お気に入りボタン生成ショートコード
 * [roro_favorite_button type="event" id="123" title="Dog Fest" url="/event/123"]
 * ========================================================== */
add_shortcode('roro_favorite_button', function($atts){
    $atts = shortcode_atts(array(
        'type'  => '',
        'id'    => '',
        'title' => '',
        'url'   => '',
    ), $atts, 'roro_favorite_button');

    $msgs = roro_fav_messages();
    if (!is_user_logged_in()) {
        return '<div class="roro-fav-hint">'.esc_html($msgs['must_login']).'</div>';
    }

    $list = roro_fav_get_list();
    $exists = roro_fav_find_index($list, $atts['type'], $atts['id']) >= 0;

    $act = $exists ? 'remove' : 'add';
    $label = $exists ? $msgs['remove'] : $msgs['add'];
    $link = add_query_arg(array(
        'roro_fav_action' => $act,
        'type'            => rawurlencode($atts['type']),
        'id'              => rawurlencode($atts['id']),
        'title'           => rawurlencode($atts['title']),
        'url'             => rawurlencode($atts['url']),
        '_wpnonce'        => wp_create_nonce('roro_fav_'.$act.'_'.$atts['type'].'_'.$atts['id']),
    ));

    return '<a class="roro-fav-btn" href="'.esc_url($link).'" style="display:inline-block;padding:6px 10px;border-radius:6px;background:#1e88e5;color:#fff;text-decoration:none;">'.esc_html($label).'</a>';
});

/* ==========================================================
 * 一覧ショートコード
 * [roro_favorites_list]
 * ========================================================== */
add_shortcode('roro_favorites_list', function(){
    $msgs = roro_fav_messages();
    if (!is_user_logged_in()) return '<div class="roro-fav-hint">'.esc_html($msgs['must_login']).'</div>';

    $list = roro_fav_get_list();
    ob_start(); ?>
    <div class="roro-fav-list" style="border:1px solid #eee;border-radius:6px;padding:10px;">
      <h3 style="margin-top:0;"><?php echo esc_html($msgs['list_title']); ?></h3>
      <?php if (empty($list)) : ?>
        <div>—</div>
      <?php else: ?>
        <ul style="margin:0;padding-left:18px;">
          <?php foreach ($list as $row):
            $rm = add_query_arg(array(
              'roro_fav_action' => 'remove',
              'type' => rawurlencode($row['type']),
              'id'   => rawurlencode($row['id']),
              '_wpnonce' => wp_create_nonce('roro_fav_remove_'.$row['type'].'_'.$row['id']),
            ));
          ?>
          <li style="margin:6px 0;">
            <?php if (!empty($row['url'])): ?>
              <a href="<?php echo esc_url($row['url']); ?>"><?php echo esc_html($row['title'] ?: ($row['type'].'#'.$row['id'])); ?></a>
            <?php else: ?>
              <?php echo esc_html($row['title'] ?: ($row['type'].'#'.$row['id'])); ?>
            <?php endif; ?>
            <a href="<?php echo esc_url($rm); ?>" style="margin-left:8px;font-size:12px;color:#c00;"><?php echo esc_html($msgs['remove']); ?></a>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
