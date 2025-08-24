<?php
if (!defined('ABSPATH')) { exit; }

/**
 * RORO_Events_Service
 * - イベント検索／カテゴリ取得
 * - 多言語ラベルの提供（UI用）
 * - 近傍距離の計算（SQL）
 */
class RORO_Events_Service {

    public function detect_lang() {
        if (isset($_GET['roro_lang'])) {
            $l = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $l = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) $l = 'ja';
            elseif (strpos($locale, 'zh') === 0) $l = 'zh';
            elseif (strpos($locale, 'ko') === 0) $l = 'ko';
            else $l = 'en';
        }
        return in_array($l, ['ja','en','zh','ko'], true) ? $l : 'en';
    }

    public function load_lang($lang) {
        $file = RORO_MAP_PATH . "lang/{$lang}.php";
        if (file_exists($file)) { require $file; if(isset($roro_events_messages)) return $roro_events_messages; }
        require RORO_MAP_PATH . "lang/en.php";
        return $roro_events_messages;
    }

    /**
     * カテゴリ一覧を取得
     * - まず RORO_EVENT_CATEGORY_MASTER（存在すれば）を優先。
     * - なければ RORO_EVENTS_MASTER の DISTINCT category を使用。
     */
    public function get_categories() {
        global $wpdb;
        $cat_tbl = $wpdb->prefix . 'RORO_EVENT_CATEGORY_MASTER';
        $ev_tbl  = $wpdb->prefix . 'RORO_EVENTS_MASTER';
        $cats = [];
        // テーブル存在チェック
        $has_cat = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $cat_tbl));
        if ($has_cat) {
            $rows = $wpdb->get_results("SELECT id, category_code, category_name FROM {$cat_tbl} ORDER BY sort_order ASC, id ASC", ARRAY_A);
            foreach($rows as $r){
                $cats[] = ['code'=>$r['category_code'], 'name'=>$r['category_name']];
            }
        } else {
            // Fallback: EVENTS の category カラムを想定
            $has_ev = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $ev_tbl));
            if ($has_ev) {
                $rows = $wpdb->get_col("SELECT DISTINCT category FROM {$ev_tbl} WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
                foreach($rows as $c){
                    $cats[] = ['code'=>$c, 'name'=>$c];
                }
            }
        }
        return $cats;
    }

    /**
     * イベント検索
     * @param array $args = [q, categories[], date_from, date_to, lat, lng, radius_km, limit, offset, order_by]
     * @return array [items=>[], total=>int]
     */
    public function search_events($args) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'RORO_EVENTS_MASTER';

        // パラメータ整形
        $q          = isset($args['q']) ? sanitize_text_field($args['q']) : '';
        $categories = isset($args['categories']) ? (array)$args['categories'] : [];
        $date_from  = isset($args['date_from']) ? sanitize_text_field($args['date_from']) : '';
        $date_to    = isset($args['date_to']) ? sanitize_text_field($args['date_to']) : '';
        $lat        = isset($args['lat']) ? floatval($args['lat']) : null;
        $lng        = isset($args['lng']) ? floatval($args['lng']) : null;
        $radius_km  = isset($args['radius_km']) ? floatval($args['radius_km']) : 0.0;
        $limit      = isset($args['limit']) ? max(1, min(200, intval($args['limit']))) : 100;
        $offset     = isset($args['offset']) ? max(0, intval($args['offset'])) : 0;
        $order_by   = isset($args['order_by']) ? sanitize_text_field($args['order_by']) : 'date';

        // カラム存在を想定: id, title, description, start_time, end_time, category, latitude, longitude, address
        $wheres = ["1=1"];
        $params = [];
        if ($q) {
            $wheres[] = "(title LIKE %s OR description LIKE %s OR address LIKE %s)";
            $like = '%' . $wpdb->esc_like($q) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '%s'));
            $wheres[] = "category IN ($placeholders)";
            foreach($categories as $c){ $params[] = sanitize_text_field($c); }
        }
        if ($date_from) {
            $wheres[] = "DATE(start_time) >= %s";
            $params[] = $date_from;
        }
        if ($date_to) {
            $wheres[] = "DATE(start_time) <= %s";
            $params[] = $date_to;
        }

        $select = "SELECT SQL_CALC_FOUND_ROWS id, title, description, start_time, end_time, category, latitude, longitude, address";
        $order_clause = " ORDER BY start_time ASC, id ASC";
        $having = "";
        if ($lat !== null and $lng !== null and $radius_km > 0) {
            // Haversine式（MySQL）
            $select .= ",
                (6371 * ACOS( LEAST(1, COS(RADIANS(%f)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(latitude)) ))) ) AS distance_km";
            $select = sprintf($select, $lat, $lng, $lat);
            $having = $wpdb->prepare(" HAVING distance_km <= %f", $radius_km);
            $order_clause = ($order_by === 'distance') ? " ORDER BY distance_km ASC, start_time ASC" : $order_clause;
        }

        $sql = $select . " FROM {$tbl} WHERE " . implode(' AND ', $wheres) . $having . $order_clause . $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        $total = intval($wpdb->get_var("SELECT FOUND_ROWS()"));

        // フォーマット
        $items = [];
        foreach($rows as $r) {
            $items[] = [
                'id'          => intval($r['id']),
                'title'       => $r['title'],
                'description' => $r['description'],
                'start_time'  => $r['start_time'],
                'end_time'    => $r['end_time'],
                'category'    => $r['category'],
                'latitude'    => floatval($r['latitude']),
                'longitude'   => floatval($r['longitude']),
                'address'     => $r['address'],
                'distance_km' => isset($r['distance_km']) ? floatval($r['distance_km']) : null,
            ];
        }
        return ['items'=>$items, 'total'=>$total];
    }
}
