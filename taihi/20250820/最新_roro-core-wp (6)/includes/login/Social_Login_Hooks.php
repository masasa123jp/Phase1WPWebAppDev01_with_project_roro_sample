<?php
/**
 * ソーシャルログイン拡張
 *
 * @package RoroCore
 */

namespace RoroCore\Login;

class Social_Login_Hooks {

	public static function init(): void {
		add_action( 'wp_login', [ self::class, 'capture_social_meta' ], 10, 2 );
	}

	/**
	 * Social Login プラグインがセットした一時データをユーザーメタへ保存
	 */
	public static function capture_social_meta( string $user_login, \WP_User $user ): void {
		if ( isset( $_SERVER['HTTP_X_SOCIAL_PROVIDER'] ) ) {
			update_user_meta( $user->ID, 'social_provider', sanitize_text_field( $_SERVER['HTTP_X_SOCIAL_PROVIDER'] ) );
		}
	}

}

Social_Login_Hooks::init();
