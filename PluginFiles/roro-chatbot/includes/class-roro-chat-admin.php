<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Chat_Admin {
    public function render_settings_page(){
        if (!current_user_can('manage_options')) return;
        $updated = false;
        if (isset($_POST['roro_chat_save']) && check_admin_referer('roro_chat_settings')){
            update_option('roro_chat_provider', sanitize_text_field($_POST['roro_chat_provider'] ?? 'echo'));
            update_option('roro_chat_openai_api_key', trim($_POST['roro_chat_openai_api_key'] ?? ''));
            update_option('roro_chat_openai_model', sanitize_text_field($_POST['roro_chat_openai_model'] ?? 'gpt-4o-mini'));
            update_option('roro_chat_dify_api_key', trim($_POST['roro_chat_dify_api_key'] ?? ''));
            update_option('roro_chat_dify_base', esc_url_raw($_POST['roro_chat_dify_base'] ?? ''));
            $updated = true;
        }
        $provider = get_option('roro_chat_provider', 'echo');
        $M = (new RORO_Chat_Service())->load_lang( (new RORO_Chat_Service())->detect_lang() );
        ?>
        <div class="wrap">
          <h1>RORO Chatbot</h1>
          <?php if ($updated): ?><div class="updated notice"><p><?php echo esc_html($M['saved']); ?></p></div><?php endif; ?>
          <form method="post">
            <?php wp_nonce_field('roro_chat_settings'); ?>
            <table class="form-table">
              <tr>
                <th scope="row"><?php echo esc_html($M['provider']); ?></th>
                <td>
                  <label><input type="radio" name="roro_chat_provider" value="echo" <?php checked($provider,'echo'); ?>/> ECHO（フォールバック）</label><br/>
                  <label><input type="radio" name="roro_chat_provider" value="openai" <?php checked($provider,'openai'); ?>/> OpenAI</label><br/>
                  <label><input type="radio" name="roro_chat_provider" value="dify" <?php checked($provider,'dify'); ?>/> Dify</label>
                </td>
              </tr>
              <tr>
                <th scope="row">OpenAI API Key</th>
                <td><input type="password" name="roro_chat_openai_api_key" value="<?php echo esc_attr(get_option('roro_chat_openai_api_key','')); ?>" size="50"/></td>
              </tr>
              <tr>
                <th scope="row">OpenAI Model</th>
                <td><input type="text" name="roro_chat_openai_model" value="<?php echo esc_attr(get_option('roro_chat_openai_model','gpt-4o-mini')); ?>" size="30"/></td>
              </tr>
              <tr>
                <th scope="row">Dify API Key</th>
                <td><input type="password" name="roro_chat_dify_api_key" value="<?php echo esc_attr(get_option('roro_chat_dify_api_key','')); ?>" size="50"/></td>
              </tr>
              <tr>
                <th scope="row">Dify Base URL</th>
                <td><input type="text" name="roro_chat_dify_base" value="<?php echo esc_attr(get_option('roro_chat_dify_base','')); ?>" size="50" placeholder="https://api.dify.ai"/></td>
              </tr>
            </table>
            <p class="submit"><button type="submit" name="roro_chat_save" class="button button-primary"><?php echo esc_html($M['save']); ?></button></p>
          </form>
        </div>
        <?php
    }
}
