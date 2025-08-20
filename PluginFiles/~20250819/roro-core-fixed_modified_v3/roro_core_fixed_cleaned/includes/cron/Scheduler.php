<?php
/**
 * WP‑Cron スケジューラ
 *
 * @package RoroCore
 */

namespace RoroCore\Cron;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	const HOOK_WEEKLY_PUSH = 'roro_core_weekly_push';

	/**
	 * フック登録
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', [ self::class, 'add_weekly_schedule' ] );
		add_action( 'init', [ self::class, 'register_event' ] );
		add_action( self::HOOK_WEEKLY_PUSH, [ self::class, 'handle_weekly_push' ] );
	}

	/**
	 * 週次スケジュールを追加（存在しない場合のみ）
	 */
	public static function add_weekly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'roro-core' ),
			];
		}
		return $schedules;
	}

	/**
	 * イベント登録
	 */
	public static function register_event(): void {
		if ( ! wp_next_scheduled( self::HOOK_WEEKLY_PUSH ) ) {
			wp_schedule_event( strtotime( 'next monday 04:00' ), 'weekly', self::HOOK_WEEKLY_PUSH ); // 月曜 4:00 JST 相当 :contentReference[oaicite:1]{index=1}
		}
	}

	/**
	 * 実際の処理
	 */
	public static function handle_weekly_push(): void {
		/**
		 * 例: メール/LINE 送信を別途ハンドラに委譲
		 */
		do_action( 'roro_core/send_weekly_advice' );
	}
}

Scheduler::init();
