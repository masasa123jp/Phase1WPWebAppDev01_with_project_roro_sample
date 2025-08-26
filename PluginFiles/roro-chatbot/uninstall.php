<?php
/**
 * Uninstall routine for the RORO Chatbot plugin.
 *
 * When the plugin is deleted via the WordPress admin this file is
 * executed. It should clean up any data stored in the options table so
 * that no orphaned settings remain. To avoid accidental deletion of user
 * conversations we only remove configuration options here.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('roro_chat_provider');
delete_option('roro_chat_openai_api_key');
delete_option('roro_chat_openai_model');
delete_option('roro_chat_dify_api_key');
delete_option('roro_chat_dify_base');