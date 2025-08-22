<?php
namespace RoroCore;

class Cron_Scheduler {
	const HOOK_WEEKLY_PUSH = 'roro_weekly_push';

	public static function init(): void {
		add_action( 'init',  [ self::class, 'schedule_events' ] );
		add_action( self::HOOK_WEEKLY_PUSH, [ self::class, 'handle_weekly_push' ] );
	}

	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( self::HOOK_WEEKLY_PUSH ) ) {
			// XServer では訪問トリガが少ないサイトも想定し、weekly → twicedaily へ短縮
			wp_schedule_event( time(), 'twicedaily', self::HOOK_WEEKLY_PUSH );
		}
	}

	public static function handle_weekly_push(): void {
		// 本番は LINE / FCM 呼び出し。ここではログのみ。
		error_log( '[RoRo] Cron executed ' . gmdate( 'c' ) );
	}
}
Cron_Scheduler::init();
