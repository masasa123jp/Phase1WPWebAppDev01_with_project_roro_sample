<?php
declare(strict_types=1);

namespace Roro;

defined('ABSPATH') || exit;

/**
 * DB アクセスラッパー
 * DDL_20250822.sql の RORO_MAP_FAVORITE を想定。
 * 実テーブル名が接頭辞付きの場合は $table を合わせてください。
 */
final class Roro_DB
{
    /**
     * 暫定: WPユーザーID -> RORO顧客ID 変換
     * 本来は RORO_USER_LINK_WP（customer_id, wp_user_id）で解決する想定。
     * ここでは簡易に「WPユーザーID == customer_id」と仮定。
     */
    private static function mapWpUserToCustomerId(int $wpUserId): int
    {
        return $wpUserId;
    }

    /** お気に入り一覧取得 */
    public static function favoritesListByWpUserId(int $wpUserId): array
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $customerId = self::mapWpUserToCustomerId($wpUserId);
        $table = 'RORO_MAP_FAVORITE'; // 必要に応じて $wpdb->prefix を付与

        $sql = "SELECT favorite_id, target_type, source_id, label, lat, lng, created_at
                  FROM {$table}
                 WHERE customer_id = %d AND isVisible = 1
              ORDER BY favorite_id DESC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $customerId), ARRAY_A);
        return $rows ?: [];
    }

    /** お気に入り追加（戻り値: 新規 favorite_id） */
    public static function favoritesAdd(
        int $wpUserId, string $targetType, ?string $sourceId, ?string $label, ?float $lat, ?float $lng
    ): int {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $customerId = self::mapWpUserToCustomerId($wpUserId);
        $table = 'RORO_MAP_FAVORITE';

        $wpdb->insert(
            $table,
            [
                'customer_id' => $customerId,
                'target_type' => $targetType,
                'source_id'   => $sourceId,
                'label'       => $label,
                'lat'         => $lat,
                'lng'         => $lng,
                'isVisible'   => 1,
                'created_at'  => current_time('mysql', 1), // UTC
            ],
            ['%d','%s','%s','%s','%f','%f','%d','%s']
        );

        return (int)$wpdb->insert_id;
    }

    /** お気に入り削除（論理削除） */
    public static function favoritesRemove(int $wpUserId, int $favoriteId): bool
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $customerId = self::mapWpUserToCustomerId($wpUserId);
        $table = 'RORO_MAP_FAVORITE';

        $updated = $wpdb->update(
            $table,
            ['isVisible' => 0],
            ['favorite_id' => $favoriteId, 'customer_id' => $customerId],
            ['%d'],
            ['%d','%d']
        );

        return $updated !== false && $updated > 0;
    }
}
