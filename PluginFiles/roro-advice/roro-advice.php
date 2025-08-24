<?php
/**
 * Plugin Name: RORO Advice
 * Description: アドバイス（CPT）を管理し、ショートコード/RESTでランダム提供。CSVインポート/エクスポートも対応。
 * Version: 1.2.0
 * Author: RORO Dev Team
 * Text Domain: roro-advice
 */

if (!defined('ABSPATH')) exit;

define('RORO_ADVICE_POST_TYPE', 'roro_advice');
define('RORO_ADVICE_TAX_CLASS', 'roro_breed_class'); // A〜H 等の分類を想定

// --- 有効化：リライト更新 & 既定ターム用意 ------------------------------------------------
register_activation_hook(__FILE__, function() {
  roro_advice_register_cpt_and_tax();
  flush_rewrite_rules();

  // 既定クラス（A〜H）を準備（存在しなければ）
  $classes = array('A','B','C','D','E','F','G','H');
  foreach ($classes as $cl) {
    if (!term_exists($cl, RORO_ADVICE_TAX_CLASS)) {
      wp_insert_term($cl, RORO_ADVICE_TAX_CLASS);
    }
  }
});

// --- CPT & タクソノミ登録 -----------------------------------------------------------------
add_action('init', 'roro_advice_register_cpt_and_tax');
function roro_advice_register_cpt_and_tax() {
  register_post_type(RORO_ADVICE_POST_TYPE, array(
    'labels' => array(
      'name'          => 'アドバイス',
      'singular_name' => 'アドバイス',
      'add_new_item'  => '新規アドバイスを追加',
      'edit_item'     => 'アドバイスを編集',
    ),
    'public'       => true,
    'show_ui'      => true,
    'show_in_menu' => true,
    'supports'     => array('title', 'editor', 'excerpt', 'author'),
    'show_in_rest' => true,
    'menu_icon'    => 'dashicons-lightbulb',
  ));

  register_taxonomy(RORO_ADVICE_TAX_CLASS, RORO_ADVICE_POST_TYPE, array(
    'labels' => array(
      'name' => '犬種クラス',
    ),
    'hierarchical' => true,
    'show_ui'      => true,
    'show_in_rest' => true,
  ));
}

// --- ランダム取得（内部API） --------------------------------------------------------------
function roro_advice_pick_random($args = array()) {
  $defaults = array(
    'class' => null,
    'limit' => 1,
  );
  $args = wp_parse_args($args, $defaults);

  $q = array(
    'post_type'      => RORO_ADVICE_POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => (int) $args['limit'],
    'orderby'        => 'rand',
    'no_found_rows'  => true,
  );
  if (!empty($args['class'])) {
    $q['tax_query'] = array(
      array(
        'taxonomy' => RORO_ADVICE_TAX_CLASS,
        'field'    => 'name',
        'terms'    => (array) $args['class'],
      )
    );
  }
  $wpq = new WP_Query($q);
  return $wpq->posts;
}

// --- ショートコード [roro_advice class="A" limit="1"] -----------------------------------
add_shortcode('roro_advice', function ($atts = array()) {
  $atts = shortcode_atts(array(
    'class' => '',
    'limit' => 1,
    'tag'   => 'blockquote', // 出力タグ
  ), $atts, 'roro_advice');

  $posts = roro_advice_pick_random(array(
    'class' => $atts['class'] ? explode(',', $atts['class']) : null,
    'limit' => max(1, (int) $atts['limit']),
  ));

  if (empty($posts)) {
    return '<div class="roro-advice-empty">'.esc_html__('アドバイスが見つかりません。', 'roro-advice').'</div>';
  }

  $tag = preg_replace('/[^a-z0-9:_-]/i', '', $atts['tag']); // 簡易制限
  $out = '';
  foreach ($posts as $p) {
    $text = apply_filters('the_content', $p->post_content);
    $out .= sprintf('<%1$s class="roro-advice-item">%2$s</%1$s>', $tag, $text);
  }
  return $out;
});

// --- REST: /wp-json/roro/v1/advice/random?class=A&limit=1 --------------------------------
add_action('rest_api_init', function () {
  register_rest_route('roro/v1', '/advice/random', array(
    'methods'  => WP_REST_Server::READABLE,
    'callback' => function (WP_REST_Request $req) {
      $class = $req->get_param('class');
      $limit = (int) ($req->get_param('limit') ?: 1);
      $posts = roro_advice_pick_random(array(
        'class' => $class ? explode(',', $class) : null,
        'limit' => max(1, $limit),
      ));
      $data = array();
      foreach ($posts as $p) {
        $data[] = array(
          'id'      => $p->ID,
          'title'   => get_the_title($p),
          'content' => wpautop($p->post_content),
          'class'   => wp_get_post_terms($p->ID, RORO_ADVICE_TAX_CLASS, array('fields' => 'names')),
          'link'    => get_permalink($p),
        );
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true',
  ));
});

// --- 管理：インポート/エクスポート（CSV） ------------------------------------------------
add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=' . RORO_ADVICE_POST_TYPE,
    'インポート/エクスポート',
    'インポート/エクスポート',
    'edit_posts',
    'roro-advice-io',
    'roro_advice_render_io_page'
  );
});

function roro_advice_render_io_page() {
  if (!current_user_can('edit_posts')) {
    wp_die('権限がありません。');
  }

  $notice = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['roro_advice_export'])) {
      check_admin_referer('roro_advice_io', '_roro_nonce');
      // エクスポート：最新1000件（必要に応じて増減）
      $q = new WP_Query(array(
        'post_type'      => RORO_ADVICE_POST_TYPE,
        'post_status'    => 'any',
        'posts_per_page' => 1000,
        'no_found_rows'  => true,
      ));
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="roro-advice-export.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, array('title','content','class'));
      foreach ($q->posts as $p) {
        $classes = wp_get_post_terms($p->ID, RORO_ADVICE_TAX_CLASS, array('fields' => 'names'));
        fputcsv($out, array($p->post_title, wp_strip_all_tags($p->post_content), implode('|', $classes)));
      }
      fclose($out);
      exit;
    }

    if (isset($_POST['roro_advice_import'])) {
      check_admin_referer('roro_advice_io', '_roro_nonce');
      if (!empty($_FILES['csv']['tmp_name'])) {
        $fp = fopen($_FILES['csv']['tmp_name'], 'r');
        if ($fp) {
          $row = 0;
          while (($cols = fgetcsv($fp)) !== false) {
            $row++;
            if ($row === 1 && implode(',', $cols) === 'title,content,class') {
              continue; // header
            }
            $title = sanitize_text_field($cols[0] ?? '');
            $content = wp_kses_post($cols[1] ?? '');
            $class_str = sanitize_text_field($cols[2] ?? '');
            if (!$title && !$content) continue;

            $post_id = wp_insert_post(array(
              'post_type'   => RORO_ADVICE_POST_TYPE,
              'post_status' => 'publish',
              'post_title'  => $title ?: wp_trim_words($content, 8, '...'),
              'post_content'=> $content,
            ));
            if (!is_wp_error($post_id) && $class_str) {
              $classes = array_filter(array_map('trim', explode('|', $class_str)));
              foreach ($classes as $name) {
                if (!term_exists($name, RORO_ADVICE_TAX_CLASS)) {
                  wp_insert_term($name, RORO_ADVICE_TAX_CLASS);
                }
              }
              wp_set_post_terms($post_id, $classes, RORO_ADVICE_TAX_CLASS, false);
            }
          }
          fclose($fp);
          $notice = '<div class="notice notice-success"><p>インポート完了。</p></div>';
        } else {
          $notice = '<div class="notice notice-error"><p>CSVを読み込めませんでした。</p></div>';
        }
      } else {
        $notice = '<div class="notice notice-error"><p>ファイルが選択されていません。</p></div>';
      }
    }
  }

  echo '<div class="wrap"><h1>アドバイス：インポート/エクスポート</h1>';
  echo $notice;

  echo '<h2>エクスポート</h2>';
  echo '<form method="post">';
  wp_nonce_field('roro_advice_io', '_roro_nonce');
  echo '<p>最新 1000 件を CSV でダウンロードします。</p>';
  submit_button('CSVをダウンロード', 'secondary', 'roro_advice_export', false);
  echo '</form>';

  echo '<hr><h2>インポート</h2>';
  echo '<form method="post" enctype="multipart/form-data">';
  wp_nonce_field('roro_advice_io', '_roro_nonce');
  echo '<p><input type="file" name="csv" accept=".csv" required></p>';
  submit_button('CSVを読み込む', 'primary', 'roro_advice_import', false);
  echo '</form>';

  echo '</div>';
}
