<?php
/**
 * RORO Auth - Chinese (Simplified) messages
 */
$roro_auth_messages = array(
    // ===== UI Titles =====
    'login_title'                 => '登录',
    'signup_title'                => '创建账户',
    'social_login_title'          => '使用社交账号登录',
    'settings_title'              => 'RORO Auth 设置',
    'section_google'              => 'Google OAuth 设置',
    'section_line'                => 'LINE OAuth 设置',

    // ===== Fields & Labels =====
    'username'                    => '用户名',
    'email'                       => '邮箱',
    'password'                    => '密码',
    'password_confirm'            => '确认密码',
    'remember_me'                 => '记住我',
    'agree_terms'                 => '同意服务条款',
    'required_mark'               => '必填',
    'optional_mark'               => '可选',

    // ===== Buttons =====
    'login_button'                => '登录',
    'signup_button'               => '注册',
    'logout_button'               => '退出登录',
    'save_button'                 => '保存',
    'back_button'                 => '返回',

    // ===== Links / Helpers =====
    'have_account'                => '已经有账户？前往登录',
    'no_account'                  => '还没有账户？立即注册',
    'forgot_password'             => '忘记密码？',

    // ===== Social Buttons =====
    'login_with_google'           => '使用 Google 登录',
    'login_with_line'             => '使用 LINE 登录',

    // ===== Placeholders =====
    'placeholder_username'        => '例如：zhang_san',
    'placeholder_email'           => 'you@example.com',
    'placeholder_password'        => '建议至少 8 位字符',
    'placeholder_password_confirm'=> '再次输入相同密码',

    // ===== Success Messages =====
    'success_signup'              => '账户已创建，已自动登录。',
    'success_login'               => '登录成功。',
    'success_logout'              => '已退出登录。',
    'success_settings_saved'      => '设置已保存。',

    // ===== Generic Errors =====
    'error_required'              => '存在未填写的必填项。',
    'error_invalid_email'         => '邮箱格式不正确。',
    'error_password_short'        => '密码过短（建议不低于 8 位）。',
    'error_password_mismatch'     => '两次输入的密码不一致。',
    'error_username_exists'       => '该用户名已被占用。',
    'error_email_exists'          => '该邮箱已被注册。',
    'error_terms_unchecked'       => '需要同意服务条款。',
    'error_login_failed'          => '用户名或密码错误。',
    'error_nonce'                 => '请求无效（nonce 验证失败）。',
    'error_unknown'               => '发生未知错误，请稍后再试。',

    // ===== OAuth Errors =====
    'error_oauth_generic'         => '社交登录过程中发生错误。',
    'error_oauth_state'           => '社交登录状态验证失败（state 不一致）。',
    'error_oauth_token'           => '获取访问令牌失败。',
    'error_oauth_profile'         => '获取用户信息失败。',
    'error_oauth_email_missing'   => '无法获取邮箱，请尝试其他登录方式。',

    // ===== Settings Labels =====
    'google_client_id'            => 'Google Client ID',
    'google_client_secret'        => 'Google Client Secret',
    'line_client_id'              => 'LINE Channel ID',
    'line_client_secret'          => 'LINE Channel Secret',
    'redirect_url_hint'           => '请将以下地址配置为各平台的回调（Redirect URL）：',

    // ===== Misc =====
    'or'                          => '或',
    'and'                         => '和',
    'separator'                   => '/',
    'loading'                     => '加载中…',
);
