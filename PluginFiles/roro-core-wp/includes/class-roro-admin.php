<?php
declare(strict_types=1);

namespace Roro;

defined('ABSPATH') || exit;

/**
 * 管理メニュー／設定ページ
 */
final class Roro_Admin
{
    public static function register_menu(): void
    {
        \add_menu_page(
            'RORO Settings',
            'RORO',
            'manage_options',
            'roro-core-settings',
            [self::class, 'render_page'],
            'dashicons-pets',
            59
        );
    }

    public static function render_page(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die('You do not have sufficient permissions to access this page.');
        }

        $opts = (array) \get_option('roro_core_settings', [
            'ai_enabled'   => 0,
            'ai_base_url'  => '',
            'map_api_key'  => '',
            'public_pages' => [],
        ]);

        // 保存処理
        if (isset($_POST['roro_submit'])) {
            \check_admin_referer('roro_core_settings');

            $ai_enabled   = isset($_POST['ai_enabled']) ? 1 : 0;
            $ai_base_url  = isset($_POST['ai_base_url']) ? \esc_url_raw((string)$_POST['ai_base_url']) : '';
            $map_api_key  = isset($_POST['map_api_key']) ? \sanitize_text_field((string)$_POST['map_api_key']) : '';
            $public_pages = isset($_POST['public_pages'])
                ? array_map('\sanitize_text_field', (array)$_POST['public_pages'])
                : [];

            $opts = [
                'ai_enabled'   => $ai_enabled,
                'ai_base_url'  => $ai_base_url,
                'map_api_key'  => $map_api_key,
                'public_pages' => $public_pages,
            ];
            \update_option('roro_core_settings', $opts);

            echo '<div class="updated"><p>Saved.</p></div>';
        }

        ?>
        <div class="wrap">
          <h1>RORO Settings</h1>
          <form method="post">
            <?php \wp_nonce_field('roro_core_settings'); ?>

            <table class="form-table" role="presentation">
              <tr>
                <th scope="row">AI features</th>
                <td>
                  <label>
                    <input type="checkbox" name="ai_enabled" value="1" <?php echo \checked(1, (int)($opts['ai_enabled'] ?? 0), false); ?>>
                    Enable
                  </label>
                </td>
              </tr>

              <tr>
                <th scope="row">AI Base URL</th>
                <td>
                  <input type="url" class="regular-text" name="ai_base_url"
                         value="<?php echo \esc_attr((string)($opts['ai_base_url'] ?? '')); ?>">
                </td>
              </tr>

              <tr>
                <th scope="row">Map API Key</th>
                <td>
                  <input type="text" class="regular-text" name="map_api_key"
                         value="<?php echo \esc_attr((string)($opts['map_api_key'] ?? '')); ?>">
                </td>
              </tr>

              <tr>
                <th scope="row">Public Pages (slugs)</th>
                <td>
                  <input type="text" class="regular-text" name="public_pages[]"
                         placeholder="example-page"
                         value="<?php echo \esc_attr(isset($opts['public_pages'][0]) ? (string)$opts['public_pages'][0] : ''); ?>">
                  <p class="description">ログイン不要で公開するページのスラッグ。必要に応じて複数入力 UI 化を検討。</p>
                </td>
              </tr>
            </table>

            <?php \submit_button('Save', 'primary', 'roro_submit'); ?>
          </form>
        </div>
        <?php
    }
}
