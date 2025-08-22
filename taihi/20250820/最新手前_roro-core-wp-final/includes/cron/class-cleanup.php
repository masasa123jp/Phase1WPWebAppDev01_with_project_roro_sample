<?php
declare( strict_types=1 );

namespace RoroCore\Cron;

class Cleanup {

	public const HOOK = 'roro_cleanup_daily';

	public static function init(): void {
		add_action( 'init', [ self::class, 'schedule' ] );
		add_action( self::HOOK, [ self::class, 'execute' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/** 有効期限切れのトランジェントや孤立したメタデータを削除します。 */
	public static function execute(): void {
		global $wpdb;
		// 期限切れのトランジェントを削除 – WPコアでも実行されるが念のため
		delete_expired_transients();

		// 孤立した画像メタデータのクリーンアップ
		$table = $wpdb->prefix . 'roro_photo_meta';
		$wpdb->query(
			"DELETE m FROM {$table} m
			 LEFT JOIN {$wpdb->posts} p ON m.post_id = p.ID
			 WHERE p.ID IS NULL"
		);
	}
}
Cleanup::init();
