<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class RORO_Settings {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register']);
    }

    public static function menu(): void {
        add_options_page(
            __('RORO Core Settings', 'roro-core-wp'),
            __('RORO Core', 'roro-core-wp'),
            'manage_options',
            'roro-core-settings',
            [self::class, 'render']
        );
    }

    public static function register(): void {
        register_setting('roro_core_settings', 'roro_core_settings', [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default'           => [],
        ]);

        add_settings_section('roro_sec_main', __('Main', 'roro-core-wp'), '__return_false', 'roro-core-settings');

        add_settings_field('map_api_key', __('Google Maps API Key', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            printf('<input type="text" name="roro_core_settings[map_api_key]" value="%s" class="regular-text" />',
                esc_attr((string)($opt['map_api_key'] ?? ''))
            );
        }, 'roro-core-settings', 'roro_sec_main');

        add_settings_field('ai_enabled', __('Enable AI', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            $checked = !empty($opt['ai_enabled']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="roro_core_settings[ai_enabled]" value="1" '.$checked.' /> ' . esc_html__('Enable', 'roro-core-wp') . '</label>';
        }, 'roro-core-settings', 'roro_sec_main');

        add_settings_field('ai_provider', __('AI Provider', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            $val = (string)($opt['ai_provider'] ?? 'none');
            echo '<select name="roro_core_settings[ai_provider]">';
            foreach (['none'=>'None','openai'=>'OpenAI','dify'=>'Dify'] as $k=>$v) {
                printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($k, $val, false), esc_html($v));
            }
            echo '</select>';
        }, 'roro-core-settings', 'roro_sec_main');

        add_settings_field('ai_base_url', __('AI Base URL', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            printf('<input type="text" name="roro_core_settings[ai_base_url]" value="%s" class="regular-text" />',
                esc_attr((string)($opt['ai_base_url'] ?? ''))
            );
        }, 'roro-core-settings', 'roro_sec_main');

        add_settings_field('ai_api_key', __('AI API Key', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            printf('<input type="password" name="roro_core_settings[ai_api_key]" value="%s" class="regular-text" autocomplete="new-password" />',
                esc_attr((string)($opt['ai_api_key'] ?? ''))
            );
        }, 'roro-core-settings', 'roro_sec_main');

        add_settings_field('supported_locales', __('Supported Locales (CSV)', 'roro-core-wp'), function() {
            $opt = get_option('roro_core_settings', []);
            $val = implode(',', array_map('sanitize_text_field', (array)($opt['supported_locales'] ?? ['ja','en','zh','ko'])));
            printf('<input type="text" name="roro_core_settings[supported_locales]" value="%s" class="regular-text" />',
                esc_attr($val)
            );
        }, 'roro-core-settings', 'roro_sec_main');
    }

    public static function render(): void {
        echo '<div class="wrap"><h1>'.esc_html__('RORO Core Settings','roro-core-wp').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('roro_core_settings');
        do_settings_sections('roro-core-settings');
        submit_button();
        echo '</form></div>';
    }

    public static function sanitize(array $input): array {
        return [
            'map_api_key'       => sanitize_text_field((string)($input['map_api_key'] ?? '')),
            'ai_enabled'        => empty($input['ai_enabled']) ? 0 : 1,
            'ai_provider'       => sanitize_text_field((string)($input['ai_provider'] ?? 'none')),
            'ai_base_url'       => esc_url_raw((string)($input['ai_base_url'] ?? '')),
            'ai_api_key'        => (string)($input['ai_api_key'] ?? ''),
            'supported_locales' => array_values(array_filter(array_map('sanitize_key', explode(',', (string)($input['supported_locales'] ?? 'ja,en,zh,ko'))))),
            'public_pages'      => [], // UI未実装（将来拡張）
        ];
    }
}
