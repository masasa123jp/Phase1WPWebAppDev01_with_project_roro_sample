<?php
namespace RoroCore;

class Cron_Cleanup {

	const HOOK_CLEANUP = 'roro_daily_cleanup';

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK_CLEANUP );
		}
		add_action( self::HOOK_CLEANUP, [ self::class, 'run' ] );
	}

	public static function run() {
		global $wpdb;
		/* 例: 90 日より古い gacha_log 削除 */
		$wpdb->query( "DELETE FROM {$wpdb->prefix}roro_gacha_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)" );
		/* 未使用メディア削除など… */
	}
}
