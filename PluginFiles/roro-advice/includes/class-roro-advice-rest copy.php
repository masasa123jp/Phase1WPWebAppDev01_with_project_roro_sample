<?php
if (!defined('ABSPATH')) { exit; }

final class RORO_Advice_REST {

  public function register_routes(){
    register_rest_route('roro/v1', '/advice/random', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [$this, 'random'],
      'permission_callback' => '__return_true',
      'args' => [
        'lang' => ['type'=>'string','required'=>false],                 // ja|en|zh|ko
        'breed_category' => ['type'=>'string','required'=>false],       // A〜H
        'tags' => ['type'=>'string','required'=>false],                 // CSV: health,training,...
      ]
    ]);
  }

  /** ランダムアドバイス取得（条件があれば絞り込み） */
  public function random(\WP_REST_Request $req){
    global $wpdb;

    $lang = $req->get_param('lang');
    if (!in_array($lang, ['ja','en','zh','ko'], true)) {
      // フォールバック: サイト言語 or ja
      $lang = substr(get_locale(), 0, 2);
      if (!in_array($lang, ['ja','en','zh','ko'], true)) $lang = 'ja';
    }

    $cat = strtoupper( (string) $req->get_param('breed_category') );
    if (!in_array($cat, ['A','B','C','D','E','F','G','H'], true)) $cat = null;

    $tagsCsv = (string) $req->get_param('tags');
    $tags = array_values(array_filter(array_map('trim', explode(',', $tagsCsv))));

    // ベースSQL
    $sql = "SELECT id, lang, title, body, tags, categories
              FROM RORO_ONE_POINT_ADVICE_MASTER
             WHERE lang=%s";
    $params = [$lang];

    // 犬種カテゴリ（categories: "A,H" のようなCSV想定）
    if ($cat) {
      $sql .= " AND (categories IS NULL OR categories='' OR FIND_IN_SET(%s, categories))";
      $params[] = $cat;
    }
    // タグ絞り込み（ANDではなくORでゆるく合致）
    if ($tags) {
      $ors = [];
      foreach ($tags as $t) { $ors[] = "FIND_IN_SET(%s, tags)"; $params[] = $t; }
      $sql .= " AND (".implode(' OR ', $ors).")";
    }

    // 候補取得
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    if (!$rows) {
      // フォールバック: 条件無視で言語一致からランダム
      $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, lang, title, body, tags, categories FROM RORO_ONE_POINT_ADVICE_MASTER WHERE lang=%s",
        $lang
      ), ARRAY_A);
    }
    if (!$rows) return new \WP_REST_Response(['error'=>'not_found'], 404);

    $pick = $rows[array_rand($rows)];
    return new \WP_REST_Response(['advice'=>$pick], 200);
  }
}
