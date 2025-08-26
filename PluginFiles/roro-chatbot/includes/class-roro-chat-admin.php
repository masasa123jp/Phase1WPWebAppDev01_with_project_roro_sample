<?php
/**
 * Settings page for the RORO Chatbot plugin.
 *
 * Provides a simple form under the WordPress Settings menu where
 * administrators can configure the chat provider (Echo/fallback, OpenAI or
 * Dify) and the associated API credentials. Messages displayed on this
 * page are drawn from the translation files to support multiple UI
 * languages. Options are persisted via WordPress's options API. Nonces
 * protect the form from CSRF and only users with the manage_options
 * capability can access the page.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_Admin {
    /**
     * Render the settings page.
     *
     * This method is called from an anonymous function registered via
     * add_options_page(). It checks for form submission, processes and
     * sanitizes posted values, updates plugin options, and outputs the
     * HTML form. Labels are localized via the I18n helper to ensure the
     * correct language is displayed based on the user's preferences.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $updated = false;
        // Handle form submission
        if (isset($_POST['roro_chat_save']) && check_admin_referer('roro_chat_settings')) {
            update_option('roro_chat_provider', sanitize_text_field($_POST['roro_chat_provider'] ?? 'echo'));
            update_option('roro_chat_openai_api_key', trim($_POST['roro_chat_openai_api_key'] ?? ''));
            update_option('roro_chat_openai_model', sanitize_text_field($_POST['roro_chat_openai_model'] ?? 'gpt-4o-mini'));
            update_option('roro_chat_dify_api_key', trim($_POST['roro_chat_dify_api_key'] ?? ''));
            update_option('roro_chat_dify_base', esc_url_raw($_POST['roro_chat_dify_base'] ?? ''));
            $updated = true;
        }
        // Load language strings
        $lang = RORO_Chat_I18n::detect_lang();
        $M    = RORO_Chat_I18n::load_messages($lang);
        $provider = get_option('roro_chat_provider', 'echo');
        ?>
        <div class="wrap">
          <h1><?php echo esc_html(__('RORO Chatbot Settings', 'roro-chatbot')); ?></h1>
          <?php if ($updated): ?>
          <div class="updated notice"><p><?php echo esc_html($M['saved'] ?? __('Settings saved.', 'roro-chatbot')); ?></p></div>
          <?php endif; ?>
          <form method="post">
            <?php wp_nonce_field('roro_chat_settings'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label><?php echo esc_html($M['provider'] ?? __('Provider', 'roro-chatbot')); ?></label></th>
                <td>
                  <label><input type="radio" name="roro_chat_provider" value="echo" <?php checked($provider, 'echo'); ?>/> ECHO</label><br/>
                  <label><input type="radio" name="roro_chat_provider" value="openai" <?php checked($provider, 'openai'); ?>/> OpenAI</label><br/>
                  <label><input type="radio" name="roro_chat_provider" value="dify" <?php checked($provider, 'dify'); ?>/> Dify</label>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="roro-chat-openai-key">OpenAI API Key</label></th>
                <td><input type="password" id="roro-chat-openai-key" name="roro_chat_openai_api_key" value="<?php echo esc_attr(get_option('roro_chat_openai_api_key', '')); ?>" size="50" autocomplete="off"/></td>
              </tr>
              <tr>
                <th scope="row"><label for="roro-chat-openai-model">OpenAI Model</label></th>
                <td><input type="text" id="roro-chat-openai-model" name="roro_chat_openai_model" value="<?php echo esc_attr(get_option('roro_chat_openai_model', 'gpt-4o-mini')); ?>" size="30"/></td>
              </tr>
              <tr>
                <th scope="row"><label for="roro-chat-dify-key">Dify API Key</label></th>
                <td><input type="password" id="roro-chat-dify-key" name="roro_chat_dify_api_key" value="<?php echo esc_attr(get_option('roro_chat_dify_api_key', '')); ?>" size="50" autocomplete="off"/></td>
              </tr>
              <tr>
                <th scope="row"><label for="roro-chat-dify-base">Dify Base URL</label></th>
                <td><input type="text" id="roro-chat-dify-base" name="roro_chat_dify_base" value="<?php echo esc_attr(get_option('roro_chat_dify_base', '')); ?>" size="50" placeholder="https://api.dify.ai"/></td>
              </tr>
            </table>
            <p class="submit">
              <button type="submit" name="roro_chat_save" class="button button-primary">
                <?php echo esc_html($M['save'] ?? __('Save', 'roro-chatbot')); ?>
              </button>
            </p>
          </form>
        </div>
        <?php
    }
}