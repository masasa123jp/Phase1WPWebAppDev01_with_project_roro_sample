<?php
/**
 * Handles recurring Cron events for RoRo Core.
 *
 * @package RoroCore\Cron
 */

declare( strict_types = 1 );

namespace RoroCore\Cron;

use WP_Cron_Schedule;

class Scheduler {

	public const HOOK_GACHA_WEEKLY = 'roro_gacha_weekly';

	/**
	 * Bootstrap hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_events' ] );
		add_action( self::HOOK_GACHA_WEEKLY, [ self::class, 'send_weekly_push' ] );
		// 追加イベントはここに追記
	}

	/**
	 * Register recurring events if not already scheduled.
	 */
	public static function register_events(): void {
		if ( ! wp_next_scheduled( self::HOOK_GACHA_WEEKLY ) ) {
			wp_schedule_event( time(), 'weekly', self::HOOK_GACHA_WEEKLY ); // 'weekly' は WP 既定間隔 :contentReference[oaicite:5]{index=5}
		}
	}

	/**
	 * Example weekly task: push notification summary.
	 */
	public static function send_weekly_push(): void {
		// この中で LINE/FCM 等を実行。失敗しても do_action は止めない。
		error_log( '[RoRo] Weekly push sent @ ' . gmdate( 'c' ) );
	}
}
