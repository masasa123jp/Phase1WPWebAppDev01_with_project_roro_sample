<?php
/**
 * RORO Auth - Japanese messages
 */
$roro_auth_messages = array(
    // ===== UI Titles =====
    'login_title'                 => 'ログイン',
    'signup_title'                => '新規ユーザー登録',
    'social_login_title'          => 'ソーシャルログイン',
    'settings_title'              => 'RORO Auth 設定',
    'section_google'              => 'Google OAuth 設定',
    'section_line'                => 'LINE OAuth 設定',

    // ===== Fields & Labels =====
    'username'                    => 'ユーザー名',
    'email'                       => 'メールアドレス',
    'password'                    => 'パスワード',
    'password_confirm'            => 'パスワード（確認）',
    'remember_me'                 => 'ログイン状態を保持する',
    'agree_terms'                 => '利用規約に同意する',
    'required_mark'               => '※必須',
    'optional_mark'               => '任意',

    // ===== Buttons =====
    'login_button'                => 'ログイン',
    'signup_button'               => '登録',
    'logout_button'               => 'ログアウト',
    'save_button'                 => '保存',
    'back_button'                 => '戻る',

    // ===== Links / Helpers =====
    'have_account'                => 'すでにアカウントをお持ちの方はこちら',
    'no_account'                  => 'アカウントをお持ちでない方はこちら',
    'forgot_password'             => 'パスワードをお忘れですか？',

    // ===== Social Buttons =====
    'login_with_google'           => 'Googleでログイン',
    'login_with_line'             => 'LINEでログイン',

    // ===== Placeholders =====
    'placeholder_username'        => '例）taro_yamada',
    'placeholder_email'           => 'you@example.com',
    'placeholder_password'        => '8文字以上の半角英数字を推奨',
    'placeholder_password_confirm'=> 'もう一度パスワードを入力',

    // ===== Success Messages =====
    'success_signup'              => 'ユーザー登録が完了しました。自動的にログインしました。',
    'success_login'               => 'ログインに成功しました。',
    'success_logout'              => 'ログアウトしました。',
    'success_settings_saved'      => '設定を保存しました。',

    // ===== Generic Errors =====
    'error_required'              => '必須項目が入力されていません。',
    'error_invalid_email'         => 'メールアドレスの形式が正しくありません。',
    'error_password_short'        => 'パスワードが短すぎます（8文字以上を推奨）。',
    'error_password_mismatch'     => '確認用パスワードが一致しません。',
    'error_username_exists'       => 'そのユーザー名は既に使用されています。',
    'error_email_exists'          => 'そのメールアドレスは既に登録されています。',
    'error_terms_unchecked'       => '利用規約への同意が必要です。',
    'error_login_failed'          => 'ユーザー名かパスワードが正しくありません。',
    'error_nonce'                 => '不正なリクエストです（Nonce検証に失敗）。',
    'error_unknown'               => '不明なエラーが発生しました。時間をおいて再度お試しください。',

    // ===== OAuth Errors =====
    'error_oauth_generic'         => '外部認証でエラーが発生しました。',
    'error_oauth_state'           => '外部認証の状態確認に失敗しました（state不一致）。',
    'error_oauth_token'           => 'アクセストークンの取得に失敗しました。',
    'error_oauth_profile'         => 'ユーザー情報の取得に失敗しました。',
    'error_oauth_email_missing'   => 'メールアドレスを取得できませんでした。別のログイン方法をお試しください。',

    // ===== Settings Labels =====
    'google_client_id'            => 'Google Client ID',
    'google_client_secret'        => 'Google Client Secret',
    'line_client_id'              => 'LINE Channel ID',
    'line_client_secret'          => 'LINE Channel Secret',
    'redirect_url_hint'           => '各プロバイダのリダイレクトURLには次を登録してください：',

    // ===== Misc =====
    'or'                          => 'または',
    'and'                         => 'と',
    'separator'                   => '／',
    'loading'                     => '読み込み中…',
);
