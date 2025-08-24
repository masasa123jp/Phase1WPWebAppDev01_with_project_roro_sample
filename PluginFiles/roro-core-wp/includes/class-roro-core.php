<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class RORO_Core {

    public static function init(): void {
        add_action('init', [self::class, 'register_cpt_and_tax']);
        add_action('init', [self::class, 'register_assets']);
    }

    // ---------------------------------------------------------------------
    // CPT: roro_event + Taxonomy: roro_pref (都道府県) / メタ: 日付・緯度経度等
    // ---------------------------------------------------------------------
    public static function register_cpt_and_tax(): void {
        // タクソノミー（都道府県など）
        register_taxonomy('roro_pref', ['roro_event'], [
            'labels'       => [
                'name'          => _x('Prefectures', 'taxonomy general name', 'roro-core-wp'),
                'singular_name' => _x('Prefecture', 'taxonomy singular name', 'roro-core-wp'),
            ],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);

        // CPT: イベント
        register_post_type('roro_event', [
            'labels' => [
                'name'          => __('Pet Events', 'roro-core-wp'),
                'singular_name' => __('Pet Event', 'roro-core-wp'),
            ],
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true, // WP REST (wp/v2) でも取得可能に
            'supports'     => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon'    => 'dashicons-calendar-alt',
        ]);
    }

    // ---------------------------------------------------------------------
    // フロント資産（登録 + ローカライズデータ）
    // ---------------------------------------------------------------------
    public static function register_assets(): void {
        // CSS
        wp_register_style(
            'roro-core',
            RORO_CORE_WP_URL . 'assets/css/roro-core.css',
            [],
            RORO_CORE_WP_VER
        );

        // JS（i18n -> app の順）
        wp_register_script(
            'roro-core-i18n',
            RORO_CORE_WP_URL . 'assets/js/i18n.js',
            [],
            RORO_CORE_WP_VER,
            true
        );
        wp_register_script(
            'roro-core-app',
            RORO_CORE_WP_URL . 'assets/js/app.js',
            ['roro-core-i18n'],
            RORO_CORE_WP_VER,
            true
        );

        // ローカライズ（REST や現在ユーザー情報、翻訳辞書）
        $settings = (array) get_option('roro_core_settings', []);
        $current_user_id = get_current_user_id();

        $data = [
            'rest' => [
                'root'  => esc_url_raw(rest_url('roro/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'site' => [
                'homeUrl' => home_url('/'),
            ],
            'auth' => [
                'isLoggedIn' => is_user_logged_in(),
                'userId'     => $current_user_id ?: 0,
            ],
            'settings' => [
                'aiEnabled'       => (bool) ($settings['ai_enabled'] ?? 0),
                'aiProvider'      => (string)($settings['ai_provider'] ?? 'none'),
                'aiBaseUrl'       => (string)($settings['ai_base_url'] ?? ''),
                'googleMapsApiKey'=> (string)($settings['map_api_key'] ?? ''),
                'supportedLocales'=> array_values((array)($settings['supported_locales'] ?? ['ja','en','zh','ko'])),
            ],
            'i18n' => self::get_ui_dictionary(),
            'locale' => determine_locale(),
            'assets' => [
                'logo'       => RORO_CORE_WP_URL . 'assets/images/logo_roro.png',
                'icon_ai'    => RORO_CORE_WP_URL . 'assets/images/icon_ai.png',
                'icon_map'   => RORO_CORE_WP_URL . 'assets/images/icon_map.png',
                'icon_mag'   => RORO_CORE_WP_URL . 'assets/images/icon_magazine.png',
                'icon_fav'   => RORO_CORE_WP_URL . 'assets/images/icon_favorite.png',
                'icon_prof'  => RORO_CORE_WP_URL . 'assets/images/icon_profile.png',
                'icon_lang'  => RORO_CORE_WP_URL . 'assets/images/icon_language.png',
            ],
        ];
        wp_localize_script('roro-core-app', 'RORO_CORE_BOOT', $data);
    }

    // ---------------------------------------------------------------------
    // 多言語 UI 辞書（必要最小限の UI キー。本文等は別管理想定）
    // ---------------------------------------------------------------------
    public static function get_ui_dictionary(): array {
        return [
            'ja' => [
                'home'        => 'ホーム',
                'magazine'    => 'マガジン',
                'map'         => '地図',
                'favorites'   => 'お気に入り',
                'profile'     => 'プロフィール',
                'ai'          => 'AIアシスタント',
                'login'       => 'ログイン',
                'logout'      => 'ログアウト',
                'signup'      => '新規登録',
                'search'      => '検索',
                'keyword'     => 'キーワード',
                'prefecture'  => '都道府県',
                'addFavorite' => 'お気に入りに追加',
                'removeFavorite' => 'お気に入りから削除',
                'todayPick'   => '今日のおすすめ',
                'save'        => '保存',
                'saved'       => '保存しました',
                'error'       => 'エラーが発生しました',
            ],
            'en' => [
                'home'        => 'Home',
                'magazine'    => 'Magazine',
                'map'         => 'Map',
                'favorites'   => 'Favorites',
                'profile'     => 'Profile',
                'ai'          => 'AI Assistant',
                'login'       => 'Login',
                'logout'      => 'Logout',
                'signup'      => 'Sign up',
                'search'      => 'Search',
                'keyword'     => 'Keyword',
                'prefecture'  => 'Prefecture',
                'addFavorite' => 'Add to Favorites',
                'removeFavorite' => 'Remove from Favorites',
                'todayPick'   => 'Today’s Pick',
                'save'        => 'Save',
                'saved'       => 'Saved',
                'error'       => 'An error occurred',
            ],
            'zh' => [
                'home'        => '首页',
                'magazine'    => '杂志',
                'map'         => '地图',
                'favorites'   => '收藏',
                'profile'     => '个人资料',
                'ai'          => 'AI助手',
                'login'       => '登录',
                'logout'      => '登出',
                'signup'      => '注册',
                'search'      => '搜索',
                'keyword'     => '关键字',
                'prefecture'  => '都道府县',
                'addFavorite' => '加入收藏',
                'removeFavorite' => '取消收藏',
                'todayPick'   => '今日推荐',
                'save'        => '保存',
                'saved'       => '已保存',
                'error'       => '发生错误',
            ],
            'ko' => [
                'home'        => '홈',
                'magazine'    => '매거진',
                'map'         => '지도',
                'favorites'   => '즐겨찾기',
                'profile'     => '프로필',
                'ai'          => 'AI 어시스턴트',
                'login'       => '로그인',
                'logout'      => '로그아웃',
                'signup'      => '회원가입',
                'search'      => '검색',
                'keyword'     => '키워드',
                'prefecture'  => '도도부현',
                'addFavorite' => '즐겨찾기에 추가',
                'removeFavorite' => '즐겨찾기에서 제거',
                'todayPick'   => '오늘의 추천',
                'save'        => '저장',
                'saved'       => '저장되었습니다',
                'error'       => '오류가 발생했습니다',
            ],
        ];
    }
}
