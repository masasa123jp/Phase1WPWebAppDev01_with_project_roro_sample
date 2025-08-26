<?php
/**
 * お気に入りのサービス層クラス。
 *
 * データ処理と国際化処理をそれぞれ別クラスに委任し、
 * このクラスはそれらをまとめる役割を担います。
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Favorites_Service {
    /**
     * @var RORO_Favorites_Data データアクセスオブジェクト
     */
    private $data;

    public function __construct() {
        // 必要なクラスがまだ読み込まれていなければ読み込む
        if (!class_exists('RORO_Favorites_Data')) {
            require_once RORO_FAV_PATH . 'includes/class-roro-favorites-data.php';
        }
        if (!class_exists('RORO_Favorites_I18n')) {
            require_once RORO_FAV_PATH . 'includes/class-roro-favorites-i18n.php';
        }
        $this->data = new RORO_Favorites_Data();
    }

    /**
     * データベーステーブル名一覧を取得します。
     * @return array
     */
    public function tables(): array {
        return $this->data->tables();
    }

    /**
     * ユーザーの言語を検出します。
     * @return string
     */
    public function detect_lang(): string {
        return RORO_Favorites_I18n::detect_lang();
    }

    /**
     * 指定言語の翻訳メッセージを読み込みます。
     * @param string $lang
     * @return array
     */
    public function load_lang(string $lang): array {
        return RORO_Favorites_I18n::load_messages($lang);
    }

    /**
     * お気に入り用テーブルを作成します。
     */
    public function install_schema(): void {
        $this->data->install_schema();
    }

    /**
     * お気に入りを追加します。
     * @param int $user_id
     * @param string $type
     * @param int $target_id
     * @return string|WP_Error
     */
    public function add_favorite(int $user_id, string $type, int $target_id) {
        return $this->data->add_favorite($user_id, $type, $target_id);
    }

    /**
     * お気に入りを削除します。
     * @param int $user_id
     * @param string $type
     * @param int $target_id
     * @return string|WP_Error
     */
    public function remove_favorite(int $user_id, string $type, int $target_id) {
        return $this->data->remove_favorite($user_id, $type, $target_id);
    }

    /**
     * お気に入り一覧を取得します。
     * @param int $user_id
     * @param string $lang
     * @param string|null $target
     * @return array
     */
    public function list_favorites(int $user_id, string $lang, string $target = null): array {
        return $this->data->list_favorites($user_id, $lang, $target);
    }

    /**
     * 指定の対象がお気に入り登録済みか判定します。
     * @param int $user_id
     * @param string $type
     * @param int $target_id
     * @return bool
     */
    public function is_favorite(int $user_id, string $type, int $target_id): bool {
        return $this->data->is_favorite($user_id, $type, $target_id);
    }
}
