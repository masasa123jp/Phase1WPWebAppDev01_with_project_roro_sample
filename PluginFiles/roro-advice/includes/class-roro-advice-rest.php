<?php
/**
 * /roro/v1/advice/random?lang=ja&breed_category=A&tags=health,training
 */
defined('ABSPATH') || exit;

if (!class_exists('RORO_Advice_REST')):
final class RORO_Advice_REST {

  public function register_routes(): void {
    register_rest_route('roro/v1', '/advice/random', [
      'methods'=>\WP_REST_Server::READABLE,
      'callback'=>[$this,'random'],
      'permission_callback'=>'__return_true',
      'args'=>[
        'lang'=>['type'=>'string','required'=>false],
        'breed_category'=>['type'=>'string','required'=>false],
        'tags'=>['type'=>'string','required'=>false],
      ]
    ]);
  }

  public function random(\WP_REST_Request $req){
    global $wpdb;
    $lang = $req->get_param('lang');
    if (!in_array($lang, ['ja','en','zh','ko'], true)) {
      $lang = substr(get_locale(),0,2);
      if (!in_array($lang,['ja','en','zh','ko'],true)) $lang='ja';
    }
    $cat = strtoupper((string)$req->get_param('breed_category'));
    if (!in_array($cat, ['A','B','C','D','E','F','G','H'], true)) $cat = null;

    $tagsCsv = (string)$req->get_param('tags');
    $tags = array_values(array_filter(array_map('trim', explode(',', $tagsCsv))));

    $sql = "SELECT id, lang, title, body, tags, categories
              FROM RORO_ONE_POINT_ADVICE_MASTER
             WHERE lang=%s";
    $params = [$lang];

    if ($cat) {
      $sql .= " AND (categories IS NULL OR categories='' OR FIND_IN_SET(%s, categories))";
      $params[] = $cat;
    }
    if ($tags) {
      $ors=[]; foreach($tags as $t){ $ors[]="FIND_IN_SET(%s, tags)"; $params[]=$t; }
      $sql .= " AND (".implode(' OR ', $ors).")";
    }

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
    if ( ! $rows ) {
      // Fallback: ignore filters and fetch any advice in the requested language
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,lang,title,body,tags,categories FROM RORO_ONE_POINT_ADVICE_MASTER WHERE lang=%s", $lang ), ARRAY_A );
    }

    if ( $rows ) {
      // Pick a random record from the result set
      $pick = $rows[ array_rand( $rows ) ];
      return new \WP_REST_Response( [ 'advice' => $pick ], 200 );
    }

    // If the database contains no advice at all, fall back to built‑in messages.
    if ( class_exists( 'RORO_Advice_Service' ) ) {
      $svc = new RORO_Advice_Service();
      $message  = $svc->fallback_random_advice( 'general', $lang );
      $messages = $svc->load_lang( $lang );
      $fallback = [
        'id'         => 0,
        'lang'       => $lang,
        'title'      => isset( $messages['advice'] ) ? $messages['advice'] : '',
        'body'       => $message,
        'tags'       => '',
        'categories' => '',
      ];
      return new \WP_REST_Response( [ 'advice' => $fallback ], 200 );
    }

    return new \WP_REST_Response( [ 'error' => 'not_found' ], 404 );
  }
}
endif;
