<?php
/**
 * お気に入りデータ管理クラス。
 *
 * このクラスはお気に入りの追加・削除・一覧取得など、
 * データベースに直接アクセスする責務を持ちます。言語判定や翻訳処理は担当しません。
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Favorites_Data {
    /**
     * @var wpdb WordPressデータベースアクセスラッパー
     */
    private $db;

    /**
     * @var string 現在のテーブルの文字セットと照合順序
     */
    private $charset;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->charset = $wpdb->get_charset_collate();
    }

    /**
     * テーブル名のリストを返します。
     *
     * @return array
     */
    public function tables(): array {
        $p = $this->db->prefix;
        return [
            'fav'    => "{$p}RORO_MAP_FAVORITE",
            'spot'   => "{$p}RORO_TRAVEL_SPOT_MASTER",
            'events' => "{$p}RORO_EVENTS_MASTER",
        ];
    }

    /**
     * お気に入り用テーブルが存在しない場合に作成します。
     * ユニークキー (user_id, target_type, target_id) により重複を防ぎます。
     */
    public function install_schema(): void {
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
        // phpcs:ignore WordPress.DB
        $this->db->query($sql);
    }

    /**
     * お気に入り登録を追加します。
     *
     * @param int    $user_id    ユーザーID
     * @param string $type       対象タイプ ('spot' または 'event')
     * @param int    $target_id  対象ID
     * @return string|WP_Error 成功時は 'added' または 'duplicate'、失敗時は WP_Error
     */
    public function add_favorite(int $user_id, string $type, int $target_id) {
        if (!in_array($type, ['spot', 'event'], true)) {
            return new WP_Error('roro_fav_bad_type', 'Bad target type.');
        }
        $t = $this->tables();
        // 既存チェック
        $exists = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$t['fav']} WHERE user_id=%d AND target_type=%s AND target_id=%d",
                $user_id,
                $type,
                $target_id
            )
        );
        if ($exists > 0) {
            return 'duplicate';
        }
        $ok = $this->db->insert(
            $t['fav'],
            [
                'user_id'     => $user_id,
                'target_type' => $type,
                'target_id'   => $target_id,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s']
        );
        if (!$ok) {
            return new WP_Error('roro_fav_insert_fail', 'Insert failed.');
        }
        return 'added';
    }

    /**
     * お気に入り登録を削除します。
     *
     * @param int    $user_id   ユーザーID
     * @param string $type      対象タイプ
     * @param int    $target_id 対象ID
     * @return string|WP_Error 削除結果
     */
    public function remove_favorite(int $user_id, string $type, int $target_id) {
        if (!in_array($type, ['spot', 'event'], true)) {
            return new WP_Error('roro_fav_bad_type', 'Bad target type.');
        }
        $t = $this->tables();
        // 該当エントリを削除（存在しなくても静かに処理）
        $this->db->delete(
            $t['fav'],
            [
                'user_id'     => $user_id,
                'target_type' => $type,
                'target_id'   => $target_id,
            ],
            ['%d', '%s', '%d']
        );
        return 'removed';
    }

    /**
     * お気に入り一覧を取得します。
     *
     * 指定ユーザーのお気に入りを対象タイプ別に取得し、言語に応じてスポットテーブルの名称や住所を取得します。
     * イベントは多言語列を持たない想定で、そのまま返します。
     *
     * @param int         $user_id ユーザーID
     * @param string      $lang    言語コード
     * @param string|null $target  'spot' または 'event' で絞り込み。nullなら両方。
     * @return array リスト配列
     */
    public function list_favorites(int $user_id, string $lang, string $target = null): array {
        $t = $this->tables();
        $where = 'WHERE f.user_id = %d';
        $params = [$user_id];
        if ($target && in_array($target, ['spot', 'event'], true)) {
            $where .= ' AND f.target_type = %s';
            $params[] = $target;
        }
        // スポット: 多言語列を切り替える
        $sql_spot = "SELECT f.id, f.target_type, f.target_id, f.created_at,
                        COALESCE(s.name_{$lang}, s.name_en) AS name,
                        COALESCE(s.address_{$lang}, s.address_en) AS address,
                        COALESCE(s.description_{$lang}, s.description_en) AS description,
                        s.lat, s.lng
                     FROM {$t['fav']} f
                     JOIN {$t['spot']} s ON s.id = f.target_id
                     {$where} AND f.target_type = 'spot'
                     ORDER BY f.created_at DESC";
        $spots = $this->db->get_results(
            $this->db->prepare($sql_spot, ...$params),
            ARRAY_A
        );
        // イベント: 多言語列は存在しない想定
        $sql_evt = "SELECT f.id, f.target_type, f.target_id, f.created_at,
                        e.title AS name,
                        e.place AS address,
                        DATE_FORMAT(e.start_at, '%Y-%m-%d %H:%i') AS description,
                        e.lat, e.lng
                    FROM {$t['fav']} f
                    JOIN {$t['events']} e ON e.id = f.target_id
                    {$where} AND f.target_type = 'event'
                    ORDER BY f.created_at DESC";
        $events = $this->db->get_results(
            $this->db->prepare($sql_evt, ...$params),
            ARRAY_A
        );
        return array_merge($events ?: [], $spots ?: []);
    }

    /**
     * 指定対象がお気に入り登録済みかを判定します。
     *
     * @param int    $user_id   ユーザーID
     * @param string $type      対象タイプ
     * @param int    $target_id 対象ID
     * @return bool 登録済みの場合 true
     */
    public function is_favorite(int $user_id, string $type, int $target_id): bool {
        $t = $this->tables();
        $count = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$t['fav']} WHERE user_id=%d AND target_type=%s AND target_id=%d",
                $user_id,
                $type,
                $target_id
            )
        );
        return ($count > 0);
    }
}
