<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/locale/user_locale_manager.php
 *
 * ユーザーの言語選択を尊重するロケールマネージャ。ログイン中でユーザーの meta に roro_locale があればそれを利用し、
 * そうでない場合は管理者が設定したデフォルト言語を返し、最後に WordPress のデフォルトロケールを返します。
 *
 * @package RoroCore\Locale
 */

namespace RoroCore\Locale;

class User_Locale_Manager {
    public static function init() : void {
        add_filter( 'determine_locale', [ self::class, 'filter_locale' ] );
    }

    /**
     * ロケール判定。
     *
     * @param string $locale
     * @return string
     */
    public static function filter_locale( string $locale ) : string {
        // ユーザーごとの設定
        if ( is_user_logged_in() ) {
            $user_locale = get_user_meta( get_current_user_id(), 'roro_locale', true );
            if ( $user_locale ) {
                return $user_locale;
            }
        }
        // 管理者設定
        $options = get_option( \RoroCore\Settings\Language_Settings::OPTION_KEY );
        if ( ! empty( $options['default_language'] ) ) {
            return $options['default_language'];
        }
        // デフォルト
        return $locale;
    }
}
