<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/notifications/notification_service.php
 *
 * 週次アドバイス通知を送信するサービス。WordPress Cron により毎週日曜日に実行され、
 * メール、LINE、FCM などのチャネルに対してアドバイスを配信します。デフォルト実装ではログ出力のみ行います。
 *
 * @package RoroCore\Notifications
 */

namespace RoroCore\Notifications;

class Notification_Service {
    /** @var string Cron フック名 */
    private const CRON_HOOK = 'roro_core_send_weekly_advice';

    public function __construct() {
        add_action( 'init', [ $this, 'schedule_events' ] );
        add_action( self::CRON_HOOK, [ $this, 'send_weekly_advice' ] );
    }

    /**
     * Cronイベントを登録。まだ登録されていなければ毎週日曜の実行をスケジュールする。
     */
    public function schedule_events() : void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( strtotime( 'next Sunday' ), 'weekly', self::CRON_HOOK );
        }
    }

    /**
     * 週次アドバイスを送信する処理。メール、LINE、FCMへメッセージを送る。
     */
    public function send_weekly_advice() : void {
        $advice = apply_filters( 'roro_weekly_advice', __( 'ペットにたくさんの愛情と運動を与えてください！', 'roro-core' ) );
        $this->send_email( $advice );
        $this->send_line( $advice );
        $this->send_fcm( $advice );
    }

    /**
     * メール通知を送る。デフォルトでは管理者メールに送信。
     *
     * @param string $message 送信するメッセージ
     */
    protected function send_email( string $message ) : void {
        wp_mail( get_option( 'admin_email' ), __( '週次ペットアドバイス', 'roro-core' ), $message );
    }

    /**
     * LINE通知を送る。デフォルト実装ではログに出力。
     *
     * @param string $message 送信するメッセージ
     */
    protected function send_line( string $message ) : void {
        error_log( 'RoRo Core LINE advice: ' . $message );
    }

    /**
     * FCM通知を送る。デフォルト実装ではログに出力。
     *
     * @param string $message 送信するメッセージ
     */
    protected function send_fcm( string $message ) : void {
        error_log( 'RoRo Core FCM advice: ' . $message );
    }
}
