<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/class-photo-storage.php
 *
 * 写真ストレージ用ヘルパー。添付ファイルのメタデータをカスタムテーブルに保存・取得します。
 * Phase 1.6では変更がないものの、将来的な拡張のために保持します。
 *
 * @package RoroCore
 */

namespace RoroCore;

class Photo_Storage {
    private string $table;
    public function __construct( \wpdb $wpdb ) {
        $this->table = $wpdb->prefix . 'roro_photo_meta';
    }
    /** メタ情報を保存します。重複があれば更新します。 */
    public function save( int $post_id, string $key, $value ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$this->table} (post_id, meta_key, meta_value)
             VALUES ( %d, %s, %s )
             ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)",
            $post_id,
            $key,
            maybe_serialize( $value )
        ) );
    }
    /** 最新の写真メタを取得します（ページング可能）。 */
    public function latest( int $limit = 20 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table}
             ORDER BY id DESC
             LIMIT %d",
            $limit
        ), ARRAY_A );
    }
}
