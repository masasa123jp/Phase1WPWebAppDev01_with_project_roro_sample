<?php
/**
 * RORO Admin Settings
 *
 * 管理画面に設定ページを追加し、各種オプションを登録・保存します。
 * 主にAI連携、雑誌機能、ソーシャルログイン、公開ページの指定、対応言語リストを管理します。
 *
 * @package   roro-core-wp
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('RORO_Admin_Settings', false)):

class RORO_Admin_Settings {
    public const OPTION = 'roro_core_settings';

    /** @var self|null */
    private static $instance = null;

    /** @return self */
    public static function instance(): self {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /** 初期化（フック登録） */
    public function init(): void {
        // 設定登録
        add_action('admin_init', [$this, 'register_settings']);

        // メニュー追加（★ 親メニュー登録 & 親サブメニュー明示）
        add_action('admin_menu', function (): void {
            add_menu_page(
                __('RORO Core', 'roro-core-wp'),
                __('RORO Core', 'roro-core-wp'),
                'manage_options',
                'roro-core-wp',
                [$this, 'render_page'],
                'dashicons-admin-generic',
                58
            );

            // ★ 親ページと同スラッグのサブメニューを明示的に追加
            //    （WPの自動追加に依存せず、URL遷移の安定性を担保）
            add_submenu_page(
                'roro-core-wp',
                __('RORO Core Settings', 'roro-core-wp'),
                __('設定', 'roro-core-wp'),
                'manage_options',
                'roro-core-wp',
                [$this, 'render_page']
            );
        });

        // 管理画面のCSS・JS
        add_action('admin_enqueue_scripts', function (string $hook): void {
            if ($hook !== 'toplevel_page_roro-core-wp') {
                return;
            }
            wp_enqueue_style(
                'roro-admin',
                RORO_CORE_WP_URL . 'assets/css/admin.css',
                [],
                RORO_CORE_WP_VER
            );
            wp_enqueue_script(
                'roro-admin',
                RORO_CORE_WP_URL . 'assets/js/admin.js',
                ['jquery'],
                RORO_CORE_WP_VER,
                true
            );
        });
    }

    /** デフォルト値 */
    public static function defaults(): array {
        return [
            'ai_enabled'                  => false,
            'ai_provider'                 => 'none', // none|dify|openai
            'ai_base_url'                 => '',
            'ai_api_key'                  => '',
            'map_api_key'                 => '',
            'magazine_enable'             => true,
            'magazine_default_lang'       => 'ja', // ja|en|zh|ko
            'magazine_has_archive'       => true,
            'magazine_archive_slug'      => 'roro-magazines',
            'social_google_client_id'     => '',
            'social_google_client_secret' => '',
            'social_line_channel_id'      => '',
            'social_line_channel_secret'  => '',
            'public_pages'                => [],  // 公開ページのID配列

            // 対応する言語のリスト。空の場合はデフォルトの4言語を使用します。
            // カスタム翻訳対応のため管理画面で変更できるようにします。
            'supported_locales'           => ['ja','en','zh','ko'],
        ];
    }

    /**
     * 管理画面のフック登録
     *
     * メニューの追加やアセットの読み込みを行います。
     */
    public function register_settings(): void {
        register_setting(
            'roro_core_settings_group',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => self::defaults(),
                'show_in_rest'      => false,
            ]
        );

        // セクション: 一般
        add_settings_section(
            'roro_core_section_general',
            __('General', 'roro-core-wp'),
            function (): void {
                echo '<p>' . esc_html__('Basic application options.', 'roro-core-wp') . '</p>';
            },
            'roro-core-wp'
        );

        add_settings_field(
            'ai_enabled',
            __('Enable AI Integration', 'roro-core-wp'),
            [$this, 'field_checkbox'],
            'roro-core-wp',
            'roro_core_section_general',
            ['key' => 'ai_enabled']
        );

        add_settings_field(
            'ai_provider',
            __('AI Provider', 'roro-core-wp'),
            [$this, 'field_ai_provider'],
            'roro-core-wp',
            'roro_core_section_general',
            ['key' => 'ai_provider']
        );

        add_settings_field(
            'ai_base_url',
            __('AI Base URL', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_general',
            ['key' => 'ai_base_url', 'placeholder' => 'https://api.example.com']
        );

        add_settings_field(
            'ai_api_key',
            __('AI API Key', 'roro-core-wp'),
            [$this, 'field_password'],
            'roro-core-wp',
            'roro_core_section_general',
            ['key' => 'ai_api_key']
        );

        add_settings_field(
            'map_api_key',
            __('Google Maps API Key', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_general',
            ['key' => 'map_api_key']
        );

        // セクション: 雑誌
        add_settings_section(
            'roro_core_section_magazine',
            __('Magazine', 'roro-core-wp'),
            function (): void {
                echo '<p>' . esc_html__('Settings for magazine custom post type and REST.', 'roro-core-wp') . '</p>';
            },
            'roro-core-wp'
        );

        add_settings_field(
            'magazine_enable',
            __('Enable Magazine', 'roro-core-wp'),
            [$this, 'field_checkbox'],
            'roro-core-wp',
            'roro_core_section_magazine',
            ['key' => 'magazine_enable']
        );

        add_settings_field(
            'magazine_default_lang',
            __('Default Language', 'roro-core-wp'),
            [$this, 'field_select_lang'],
            'roro-core-wp',
            'roro_core_section_magazine',
            ['key' => 'magazine_default_lang']
        );

        add_settings_field(
            'magazine_has_archive',
            __('Has Archive', 'roro-core-wp'),
            [$this, 'field_checkbox'],
            'roro-core-wp',
            'roro_core_section_magazine',
            ['key' => 'magazine_has_archive']
        );

        add_settings_field(
            'magazine_archive_slug',
            __('Archive Slug', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_magazine',
            ['key' => 'magazine_archive_slug', 'placeholder' => 'roro-magazines']
        );

        // セクション: ソーシャル
        add_settings_section(
            'roro_core_section_social',
            __('Social Login', 'roro-core-wp'),
            function (): void {
                echo '<p>' . esc_html__('Credentials for Google / LINE, etc.', 'roro-core-wp') . '</p>';
            },
            'roro-core-wp'
        );

        add_settings_field(
            'social_google_client_id',
            __('Google Client ID', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_social',
            ['key' => 'social_google_client_id']
        );

        add_settings_field(
            'social_google_client_secret',
            __('Google Client Secret', 'roro-core-wp'),
            [$this, 'field_password'],
            'roro-core-wp',
            'roro_core_section_social',
            ['key' => 'social_google_client_secret']
        );

        add_settings_field(
            'social_line_channel_id',
            __('LINE Channel ID', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_social',
            ['key' => 'social_line_channel_id']
        );
        add_settings_field(
            'social_line_channel_secret',
            __('LINE Channel Secret', 'roro-core-wp'),
            [$this, 'field_password'],
            'roro-core-wp',
            'roro_core_section_social',
            ['key' => 'social_line_channel_secret']
        );

        // セクション: 公開ページ
        add_settings_section(
            'roro_core_section_public',
            __('Public Pages', 'roro-core-wp'),
            function (): void {
                echo '<p>' . esc_html__('Select pages that are publicly accessible.', 'roro-core-wp') . '</p>';
            },
            'roro-core-wp'
        );

        add_settings_field(
            'public_pages',
            __('Pages', 'roro-core-wp'),
            [$this, 'field_pages_multiselect'],
            'roro-core-wp',
            'roro_core_section_public',
            ['key' => 'public_pages']
        );

        // セクション: ローカライズ（対応言語）
        add_settings_section(
            'roro_core_section_i18n',
            __('Localization', 'roro-core-wp'),
            function (): void {
                echo '<p>' . esc_html__(
                    'Specify the list of supported locales for the front-end UI as a comma separated list (e.g. "ja,en,zh,ko").',
                    'roro-core-wp'
                ) . '</p>';
            },
            'roro-core-wp'
        );

        add_settings_field(
            'supported_locales',
            __('Supported Locales (CSV)', 'roro-core-wp'),
            [$this, 'field_text'],
            'roro-core-wp',
            'roro_core_section_i18n',
            ['key' => 'supported_locales', 'placeholder' => 'ja,en,zh,ko']
        );
    }

    /**
     * 入力サニタイズ
     * @param array $input
     * @return array
     */
    public function sanitize_settings(array $input): array {
        $output = self::defaults();

        $output['ai_enabled']  = !empty($input['ai_enabled']);
        $output['ai_provider'] = in_array(($input['ai_provider'] ?? 'none'), ['none','dify','openai'], true)
            ? $input['ai_provider']
            : 'none';

        $output['ai_base_url'] = isset($input['ai_base_url']) ? esc_url_raw((string)$input['ai_base_url']) : '';
        $output['ai_api_key']  = isset($input['ai_api_key']) ? wp_kses_post((string)$input['ai_api_key']) : '';

        $output['map_api_key'] = isset($input['map_api_key']) ? sanitize_text_field((string)$input['map_api_key']) : '';

        $output['magazine_enable'] = !empty($input['magazine_enable']);

        // ★ ここを修正：言語コード（文字列）を保存。in_array の結果（boolean）を保存しない。
        $output['magazine_default_lang'] = in_array(($input['magazine_default_lang'] ?? 'ja'), ['ja','en','zh','ko'], true)
            ? (string)$input['magazine_default_lang']
            : 'ja';

        $output['magazine_has_archive'] = !empty($input['magazine_has_archive']);

        $slug_input = isset($input['magazine_archive_slug']) ? sanitize_text_field((string)$input['magazine_archive_slug']) : 'roro-magazines';
        $output['magazine_archive_slug'] = sanitize_title($slug_input ?: 'roro-magazines')
            ? sanitize_title($slug_input)
            : 'roro-magazines';

        // 公開ページ（配列の数値ID化）
        $pages = $input['public_pages'] ?? [];
        $output['public_pages'] = array_values(
            array_filter(
                array_map('intval', (array)$pages),
                static function(int $v): bool {
                    return $v > 0;
                }
            )
        );

        // 対応言語リスト: カンマ区切りの文字列から配列へ
        if (isset($input['supported_locales'])) {
            $csv = (string)$input['supported_locales'];
            $codes = array_map('trim', explode(',', $csv));
            $codes = array_map(
                static function (string $v): string {
                    // 英小文字・ハイフンのみ許容（例: ja, en, zh-CN）
                    $v = strtolower($v);
                    return preg_replace('/[^a-z\-]/', '', $v) ?? '';
                },
                $codes
            );
            // 空要素を除外
            $codes = array_values(
                array_filter(
                    $codes,
                    static function (string $v): bool {
                        return $v !== '';
                    }
                )
            );
            // 重複を除く
            $codes = array_unique($codes);
            // 空の場合はデフォルト値
            $output['supported_locales'] = $codes ?: self::defaults()['supported_locales'];
        }

        // ソーシャル
        $output['social_google_client_id']     = isset($input['social_google_client_id'])     ? sanitize_text_field((string)$input['social_google_client_id'])     : '';
        $output['social_google_client_secret'] = isset($input['social_google_client_secret']) ? sanitize_text_field((string)$input['social_google_client_secret']) : '';
        $output['social_line_channel_id']      = isset($input['social_line_channel_id'])      ? sanitize_text_field((string)$input['social_line_channel_id'])      : '';
        $output['social_line_channel_secret']  = isset($input['social_line_channel_secret'])  ? sanitize_text_field((string)$input['social_line_channel_secret'])  : '';

        return $output;
    }

    /** AI Provider 選択 */
    public function field_ai_provider(array $args): void {
        $key  = (string)$args['key'];
        $opts = get_option(self::OPTION, self::defaults());
        $val  = (string)($opts[$key] ?? 'none');

        $choices = [
            'none'  => __('None', 'roro-core-wp'),
            'dify'  => __('Dify', 'roro-core-wp'),
            'openai'=> __('OpenAI', 'roro-core-wp'),
        ];
        echo '<select name="'.esc_attr(self::OPTION).'['.esc_attr($key).']">';
        foreach ($choices as $v => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($v),
                selected($val === $v, true, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /** チェックボックス汎用 */
    public function field_checkbox(array $args): void {
        $key  = (string)$args['key'];
        $opts = get_option(self::OPTION, self::defaults());
        $val  = !empty($opts[$key]);

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
            esc_attr(self::OPTION),
            esc_attr($key),
            checked(true, $val, false),
            esc_html__('Enable', 'roro-core-wp')
        );
    }

    /**
     * テキスト入力フィールド（★ 配列値→CSVで表示）
     */
    public function field_text(array $args): void {
        $key  = (string)$args['key'];
        $ph   = (string)($args['placeholder'] ?? '');
        $opts = get_option(self::OPTION, self::defaults());
        $val_raw  = $opts[$key] ?? '';
        $val = is_array($val_raw) ? implode(',', array_map('strval', $val_raw)) : (string)$val_raw;
        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s">',
            esc_attr(self::OPTION),
            esc_attr($key),
            esc_attr($val),
            esc_attr($ph)
        );
    }

    /** パスワード入力 */
    public function field_password(array $args): void {
        $key  = (string)$args['key'];
        $opts = get_option(self::OPTION, self::defaults());
        $val  = (string)($opts[$key] ?? '');
        printf(
            '<input type="password" class="regular-text" name="%1$s[%2$s]" value="%3$s" autocomplete="new-password">',
            esc_attr(self::OPTION),
            esc_attr($key),
            esc_attr($val)
        );
    }

    /** 固定ページマルチセレクト */
    public function field_pages_multiselect(array $args): void {
        $key    = (string)$args['key'];
        $opts   = get_option(self::OPTION, self::defaults());
        $values = array_map('intval', (array)($opts[$key] ?? []));

        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order'  => 'asc',
            'post_status' => ['publish','private','draft']
        ]);

        printf(
            '<select name="%1$s[%2$s][]" multiple size="8" style="min-width:320px">',
            esc_attr(self::OPTION),
            esc_attr($key)
        );
        foreach ($pages as $p) {
            printf(
                '<option value="%1$d" %2$s>%3$s (%1$d)</option>',
                (int)$p->ID,
                selected(true, in_array((int)$p->ID, $values, true), false),
                esc_html(get_the_title((int)$p->ID) ?: '(no title)')
            );
        }
        echo '</select>';
    }

    /** 言語セレクト */
    public function field_select_lang(array $args): void {
        $key  = (string)$args['key'];
        $opts = get_option(self::OPTION, self::defaults());
        $val  = (string)($opts[$key] ?? 'ja');
        $langs = ['ja'=>'Japanese','en'=>'English','zh'=>'Chinese','ko'=>'Korean'];
        echo '<select name="'.esc_attr(self::OPTION).'['.esc_attr($key).']">';
        foreach ($langs as $code=>$label) {
            printf('<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($code),
                selected($val === $code, true, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /** 設定ページを表示 */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'roro-core-wp'));
        }
        $opts = get_option(self::OPTION, self::defaults());
        ?>
        <div class="wrap roro-admin-wrap">
            <h1><?php echo esc_html__('RORO Core Settings', 'roro-core-wp'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('roro_core_settings_group');
                do_settings_sections('roro-core-wp');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

endif;
