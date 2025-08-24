<?php
/**
 * お気に入りの業務ロジック（DB操作 + 多言語）
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Favorites_Service {

    /** @var wpdb */
    private $db;
    private $charset;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->charset = $wpdb->get_charset_collate();
    }

    public function tables() {
        $p = $this->db->prefix;
        return [
            'fav'    => "{$p}RORO_MAP_FAVORITE",
            'spot'   => "{$p}RORO_TRAVEL_SPOT_MASTER",
            'events' => "{$p}RORO_EVENTS_MASTER"
        ];
    }

    public function detect_lang() {
        if (isset($_GET['roro_lang'])) {
            $l = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $l = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = determine_locale();
            if (strpos($locale, 'ja') === 0) {
                $l = 'ja';
            } elseif (strpos($locale, 'zh') === 0) {
                $l = 'zh';
            } elseif (strpos($locale, 'ko') === 0) {
                $l = 'ko';
            } else {
                $l = 'en';
            }
        }
        return in_array($l, ['ja','en','zh','ko'], true) ? $l : 'en';
    }

    public function load_lang($lang) {
        $file = RORO_FAV_PATH . "lang/{$lang}.php";
        if (file_exists($file)) {
            require $file;
            if (isset($roro_fav_messages) && is_array($roro_fav_messages)) {
                return $roro_fav_messages;
            }
        }
        require RORO_FAV_PATH . "lang/en.php";
        return $roro_fav_messages;
    }

    /**
     * スキーマ作成（存在しない場合のみ）
     * ユニークキー: (user_id, target_type, target_id)
     */
    public function install_schema() {
        $t = $this->tables();
        $sql = "CREATE TABLE IF NOT EXISTS {$t['fav']} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            target_type ENUM('spot','event') NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_target (user_id, target_type, target_id),
            KEY idx_user (user_id),
            KEY idx_type (target_type),
            KEY idx_target (target_id)
        ) {$this->charset};";
        $this->db->query($sql);
    }

    public function add_favorite($user_id, $type, $target_id) {
        if (!in_array($type, ['spot','event'], true)) {
            return new WP_Error('roro_fav_bad_type', 'Bad target type.');
        }
        // 既存チェック（事前に確認し、メッセージ用途に使う）
        $t = $this->tables();
        $exists = (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$t['fav']} WHERE user_id=%d AND target_type=%s AND target_id=%d",
            $user_id, $type, $target_id
        ));
        if ($exists > 0) {
            return 'duplicate';
        }
        $ok = $this->db->insert($t['fav'], [
            'user_id'     => $user_id,
            'target_type' => $type,
            'target_id'   => $target_id,
            'created_at'  => current_time('mysql')
        ], ['%d','%s','%d','%s']);
        if (!$ok) {
            return new WP_Error('roro_fav_insert_fail', 'Insert failed.');
        }
        return 'added';
    }

    public function remove_favorite($user_id, $type, $target_id) {
        if (!in_array($type, ['spot','event'], true)) {
            return new WP_Error('roro_fav_bad_type', 'Bad target type.');
        }
        $t = $this->tables();
        // 該当エントリを削除（存在しなくても静かに処理）
        $this->db->delete($t['fav'], [
            'user_id'     => $user_id,
            'target_type' => $type,
            'target_id'   => $target_id
        ], ['%d','%s','%d']);
        return 'removed';
    }

    /**
     * 一覧取得（言語別に名称/住所などを組み立て）
     */
    public function list_favorites($user_id, $lang, $target = null) {
        $t = $this->tables();
        $where = "WHERE f.user_id = %d";
        $params = [$user_id];
        if ($target && in_array($target, ['spot','event'], true)) {
            $where .= " AND f.target_type = %s";
            $params[] = $target;
        }
        // SPOT: 多言語名/住所/説明（なければ英語デフォルト）
        $sql_spot = "SELECT f.id, f.target_type, f.target_id, f.created_at,
                        COALESCE(s.name_${lang}, s.name_en) AS name,
                        COALESCE(s.address_${lang}, s.address_en) AS address,
                        COALESCE(s.description_${lang}, s.description_en) AS description,
                        s.lat, s.lng
                     FROM {$t['fav']} f
                     JOIN {$t['spot']} s ON s.id = f.target_id
                     ${where} AND f.target_type = 'spot'
                     ORDER BY f.created_at DESC";
        $spots = $this->db->get_results($this->db->prepare($sql_spot, ...$params), ARRAY_A);

        // EVENT: タイトル等（イベントは多言語列がない想定、そのまま格納）
        $sql_evt = "SELECT f.id, f.target_type, f.target_id, f.created_at,
                        e.title AS name,
                        e.place AS address,
                        DATE_FORMAT(e.start_at, '%Y-%m-%d %H:%i') AS description,
                        e.lat, e.lng
                    FROM {$t['fav']} f
                    JOIN {$t['events']} e ON e.id = f.target_id
                    ${where} AND f.target_type = 'event'
                    ORDER BY f.created_at DESC";
        $events = $this->db->get_results($this->db->prepare($sql_evt, ...$params), ARRAY_A);

        return array_merge($events ?: [], $spots ?: []);
    }

    /**
     * 指定ユーザーが指定対象をお気に入り登録済みか判定
     */
    public function is_favorite($user_id, $type, $target_id) {
        $t = $this->tables();
        $count = (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$t['fav']} WHERE user_id=%d AND target_type=%s AND target_id=%d",
            $user_id, $type, $target_id
        ));
        return ($count > 0);
    }
}
