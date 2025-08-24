<?php
/**
 * RORO Recommend Service - 推薦の業務ロジック層とデータ管理を担当
 *
 * 変更点:
 * - public アクセサ get_db() を追加し、外部クラス（管理画面等）から DB 接続 (wpdb) を取得可能にした。
 */
if (!defined('ABSPATH')) { exit; }

class RORO_Recommend_Service {

    /** @var wpdb $db WordPressデータベースオブジェクト（外部からは get_db() で参照） */
    private $db;

    /** @var string $charset データベースの文字セット・照合順序 */
    private $charset;

    /**
     * コンストラクタ
     * - WordPress のグローバル $wpdb を取得し、以後の DB 操作で使用する
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->charset = $wpdb->get_charset_collate();
    }

    /**
     * DB オブジェクト（wpdb）を返すアクセサ
     * - 外部クラスは $service->get_db() で DB にアクセスする（$service->db へ直接アクセスしない）
     *
     * @return wpdb
     */
    public function get_db() {
        return $this->db;
    }

    /**
     * 必要なデータベーステーブルを作成（プラグイン有効化時に実行）
     */
    public function install() {
        $tables = $this->tables();

        // アドバイスのマスタ（多言語対応フィールドを保持）
        $sql1 = "CREATE TABLE IF NOT EXISTS {$tables['advice']} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            advice_key VARCHAR(64) NOT NULL UNIQUE,
            content_ja TEXT NOT NULL,
            content_en TEXT NOT NULL,
            content_zh TEXT NOT NULL,
            content_ko TEXT NOT NULL,
            category VARCHAR(64) DEFAULT 'general',
            active TINYINT(1) DEFAULT 1,
            weight INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$this->charset};";

        // スポットのマスタ（多言語の名称・住所・説明、位置情報）
        $sql2 = "CREATE TABLE IF NOT EXISTS {$tables['spot']} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            spot_key VARCHAR(64) NOT NULL UNIQUE,
            name_ja VARCHAR(191) NOT NULL,
            name_en VARCHAR(191) NOT NULL,
            name_zh VARCHAR(191) NOT NULL,
            name_ko VARCHAR(191) NOT NULL,
            address_ja VARCHAR(191) DEFAULT '',
            address_en VARCHAR(191) DEFAULT '',
            address_zh VARCHAR(191) DEFAULT '',
            address_ko VARCHAR(191) DEFAULT '',
            description_ja TEXT DEFAULT NULL,
            description_en TEXT DEFAULT NULL,
            description_zh TEXT DEFAULT NULL,
            description_ko TEXT DEFAULT NULL,
            lat DOUBLE DEFAULT NULL,
            lng DOUBLE DEFAULT NULL,
            category VARCHAR(64) DEFAULT 'pet_friendly',
            rating FLOAT DEFAULT NULL,
            popular INT DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$this->charset};";

        // カテゴリとデータ（スポット/アドバイス）のリンクマスタ（将来拡張用）
        $sql3 = "CREATE TABLE IF NOT EXISTS {$tables['link']} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(64) NOT NULL,
            data_key VARCHAR(64) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) {$this->charset};";

        // 日次レコメンドのログ（user_id + date + lang でユニーク）
        $sql4 = "CREATE TABLE IF NOT EXISTS {$tables['log']} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            recommend_date DATE NOT NULL,
            lang VARCHAR(8) NOT NULL,
            advice_id BIGINT UNSIGNED NOT NULL,
            spot_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_date (user_id, recommend_date)
        ) {$this->charset};";

        // クエリ実行
        $this->db->query($sql1);
        $this->db->query($sql2);
        $this->db->query($sql3);
        $this->db->query($sql4);
    }

    /**
     * 初期データの投入（テーブルが空の場合に実行）
     */
    public function maybe_seed() {
        $tables = $this->tables();
        // 現在のレコード数を取得
        $advice_count = (int) $this->db->get_var("SELECT COUNT(*) FROM {$tables['advice']}");
        $spot_count   = (int) $this->db->get_var("SELECT COUNT(*) FROM {$tables['spot']}");

        if ($advice_count === 0) {
            // サンプルのアドバイスデータを挿入（各言語のコンテンツを含む）
            $this->db->query($this->db->prepare(
                "INSERT INTO {$tables['advice']} (advice_key, content_ja, content_en, content_zh, content_ko, category, active, weight) VALUES
                 (%s, %s, %s, %s, %s, %s, 1, 3),
                 (%s, %s, %s, %s, %s, %s, 1, 1),
                 (%s, %s, %s, %s, %s, %s, 1, 1)",
                // Advice 1 (カテゴリ: pet_care)
                'advice_pet_care',
                'ペットのお手入れを定期的にしましょう。', 'Take care of your pet regularly.', '定期地给宠物做护理。', '애완동물을 정기적으로 돌봐주세요.',
                'pet_care',
                // Advice 2 (カテゴリ: travel)
                'advice_travel',
                '新しい場所を探索してみましょう。', 'Try exploring a new place.', '尝试探索一个新的地方。', '새로운 장소를 탐험해 보세요.',
                'travel',
                // Advice 3 (カテゴリ: general)
                'advice_general',
                'リラックスする時間を作りましょう。', 'Make time to relax.', '留出时间放松一下。', '휴식을 취할 시간을 가지세요.',
                'general'
            ));
        }

        if ($spot_count === 0) {
            // サンプルのスポットデータを挿入（各言語フィールドを含む）
            $this->db->query($this->db->prepare(
                "INSERT INTO {$tables['spot']} (spot_key, name_ja, name_en, name_zh, name_ko, description_ja, description_en, description_zh, description_ko, lat, lng, category, rating, popular, active) VALUES
                 (%s, %s, %s, %s, %s, %s, %s, %s, %s, %f, %f, %s, %f, %d, 1),
                 (%s, %s, %s, %s, %s, %s, %s, %s, %s, %f, %f, %s, %f, %d, 1)",
                // Spot 1 (屋外: 公園)
                'spot_park',
                '近所の公園', 'Local Park', '附近的公园', '동네 공원',
                'ゆったり過ごせる緑豊かな公園です。', 'A green park where you can relax.', '这是一个适合悠闲度过时光的绿意盎然的公园。', '여유롭게 보낼 수 있는 녹음이 우거진 공원입니다.',
                35.0, 139.0, 'outdoor', 4.5, 150,
                // Spot 2 (屋内: カフェ)
                'spot_cafe',
                '人気のカフェ', 'Popular Cafe', '人气咖啡馆', '인기 카페',
                '雰囲気の良いカフェで、コーヒーが評判です。', 'A cafe with a great atmosphere and renowned coffee.', '这是一家氛围极佳且以咖啡闻名的咖啡馆。', '분위기가 좋고 커피로 유명한 카페입니다.',
                35.1, 139.1, 'indoor', 4.2, 200
            ));
        }
    }

    /**
     * 当プラグインで使用するテーブル名（プレフィックス付き）を返す
     */
    public function tables() {
        $prefix = $this->db->prefix;
        return array(
            'advice' => "{$prefix}RORO_ONE_POINT_ADVICE_MASTER",
            'spot'   => "{$prefix}RORO_TRAVEL_SPOT_MASTER",
            'link'   => "{$prefix}RORO_CATEGORY_DATA_LINK_MASTER",
            'log'    => "{$prefix}RORO_RECOMMENDATION_LOG"
        );
    }

    /**
     * 現在のユーザー環境に応じた言語コードを検出
     * 優先順位: URLクエリ (?roro_lang) / クッキー → サイトのロケール → 不明時 'en'
     */
    public function detect_lang() {
        if (isset($_GET['roro_lang'])) {
            $lang = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $lang = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) {
                $lang = 'ja';
            } elseif (strpos($locale, 'zh') === 0) {
                $lang = 'zh';
            } elseif (strpos($locale, 'ko') === 0) {
                $lang = 'ko';
            } else {
                $lang = 'en';
            }
        }
        return in_array($lang, array('ja','en','zh','ko'), true) ? $lang : 'en';
    }

    /**
     * 指定した言語のメッセージ配列を読み込む
     *
     * @param string $lang 言語コード
     * @return array 翻訳メッセージの連想配列
     */
    public function load_lang($lang) {
        $file = RORO_REC_PATH . "lang/{$lang}.php";
        if (file_exists($file)) {
            /** @noinspection PhpIncludeInspection */
            include $file;
            if (isset($roro_recommend_messages) && is_array($roro_recommend_messages)) {
                return $roro_recommend_messages;
            }
        }
        /** @noinspection PhpIncludeInspection */
        include RORO_REC_PATH . "lang/en.php";
        return isset($roro_recommend_messages) ? $roro_recommend_messages : array();
    }

    /**
     * 今日のおすすめを取得（既生成があれば再利用、無ければ生成）
     *
     * @param int $user_id
     * @param string $lang
     * @return array|null
     */
    public function get_today($user_id, $lang) {
        $tables = $this->tables();
        $today  = current_time('Y-m-d');

        // 既に当日のおすすめがあるかチェック
        $log_row = $this->db->get_row($this->db->prepare(
            "SELECT spot_id, advice_id FROM {$tables['log']}
             WHERE user_id = %d AND recommend_date = %s AND lang = %s",
            $user_id, $today, $lang
        ), ARRAY_A);

        if ($log_row) {
            $spot   = $this->get_spot($log_row['spot_id'], $lang);
            $advice = $this->get_advice($log_row['advice_id'], $lang);
            if ($spot && $advice) {
                return array(
                    'date'   => $today,
                    'lang'   => $lang,
                    'spot'   => $spot,
                    'advice' => $advice
                );
            }
        }
        // ログが無い場合は新規に推薦を生成
        return $this->generate_recommendation($user_id, $lang, $today);
    }

    /**
     * 今日のおすすめを強制再生成（既存の当日レコメンドを置き換え）
     *
     * @param int $user_id
     * @param string $lang
     * @return array|null
     */
    public function regen_today($user_id, $lang) {
        $today = current_time('Y-m-d');
        return $this->generate_recommendation($user_id, $lang, $today);
    }

    /**
     * 内部処理: 指定ユーザー・指定日について新規におすすめを生成し、ログに保存
     *
     * @param int $user_id
     * @param string $lang
     * @param string $date
     * @return array|null
     */
    private function generate_recommendation($user_id, $lang, $date) {
        $tables = $this->tables();

        // ユーザーのお気に入り済スポットを除外（お気に入りテーブルが存在する場合）
        $fav_table = $this->db->prefix . 'RORO_MAP_FAVORITE';
        $fav_ids = $this->db->get_col($this->db->prepare(
            "SELECT DISTINCT ref_id FROM {$fav_table}
             WHERE user_id = %d AND ref_type = %s",
            $user_id, 'event_spot'
        ));
        $fav_ids     = $fav_ids ? array_map('intval', $fav_ids) : array();
        $exclude_sql = '';
        if (!empty($fav_ids)) {
            $exclude_sql = ' AND s.id NOT IN (' . implode(',', $fav_ids) . ')';
        }

        // 候補スポットを取得（rating/popular優先で最大100件）
        $sql        = "SELECT s.id FROM {$tables['spot']} s
                        WHERE s.active = 1 {$exclude_sql}
                        ORDER BY COALESCE(s.rating,0) DESC, COALESCE(s.popular,0) DESC, s.id DESC
                        LIMIT 100";
        $candidates = $this->db->get_col($sql);
        if (!$candidates) {
            // データが少ない場合: 制限なしで最大100件
            $candidates = $this->db->get_col("SELECT s.id FROM {$tables['spot']} s
                                              WHERE s.active = 1
                                              ORDER BY s.id DESC
                                              LIMIT 100");
        }
        if (!$candidates) {
            return null;  // スポットが無い場合
        }

        // 候補からランダムにスポット1件選択
        $spot_id = (int) $candidates[array_rand($candidates)];

        // アドバイスもランダムに1件選択
        $advice_id = (int) $this->db->get_var("SELECT id FROM {$tables['advice']}
                                               WHERE active = 1
                                               ORDER BY RAND() LIMIT 1");
        if (!$advice_id) {
            return null;  // アドバイスが無い場合
        }

        // ログに保存（user_id+date でユニーク。存在すれば置換）
        $this->db->replace($tables['log'], array(
            'user_id'        => (int) $user_id,
            'recommend_date' => $date,
            'lang'           => $lang,
            'spot_id'        => $spot_id,
            'advice_id'      => $advice_id,
            'created_at'     => current_time('mysql')
        ));

        // 詳細情報を取得して返す
        $spot   = $this->get_spot($spot_id, $lang);
        $advice = $this->get_advice($advice_id, $lang);
        if (!$spot || !$advice) {
            return null;
        }
        return array(
            'date'   => $date,
            'lang'   => $lang,
            'spot'   => $spot,
            'advice' => $advice
        );
    }

    /**
     * 指定IDのスポット情報を取得し、言語に応じたフィールドを整形
     *
     * @param int $spot_id
     * @param string $lang
     * @return array|null
     */
    private function get_spot($spot_id, $lang) {
        $tables = $this->tables();
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$tables['spot']} WHERE id = %d",
            $spot_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        // 言語別の名称・住所・説明（無い場合は英語をフォールバック）
        $name        = !empty($row["name_{$lang}"]) ? $row["name_{$lang}"] : $row["name_en"];
        $address     = !empty($row["address_{$lang}"]) ? $row["address_{$lang}"] : $row["address_en"];
        $description = !empty($row["description_{$lang}"]) ? $row["description_{$lang}"] : $row["description_en"];

        return array(
            'id'       => (int) $row['id'],
            'name'     => $name,
            'address'  => $address,
            'desc'     => $description,
            'lat'      => isset($row['lat']) ? (float) $row['lat'] : null,
            'lng'      => isset($row['lng']) ? (float) $row['lng'] : null,
            'category' => isset($row['category']) ? $row['category'] : 'pet_friendly',
            'rating'   => isset($row['rating']) ? (float) $row['rating'] : null
        );
    }

    /**
     * 指定IDのアドバイス情報を取得し、言語に応じたテキストを整形
     *
     * @param int $advice_id
     * @param string $lang
     * @return array|null
     */
    private function get_advice($advice_id, $lang) {
        $tables = $this->tables();
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$tables['advice']} WHERE id = %d",
            $advice_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $content = !empty($row["content_{$lang}"]) ? $row["content_{$lang}"] : $row["content_en"];
        return array(
            'id'       => (int) $row['id'],
            'text'     => $content,
            'category' => isset($row['category']) ? $row['category'] : 'general'
        );
    }
}
