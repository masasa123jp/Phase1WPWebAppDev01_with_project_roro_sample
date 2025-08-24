<?php
/**
 * RORO Auth - Korean messages
 */
$roro_auth_messages = array(
    // ===== UI Titles =====
    'login_title'                 => '로그인',
    'signup_title'                => '회원가입',
    'social_login_title'          => '소셜 로그인',
    'settings_title'              => 'RORO Auth 설정',
    'section_google'              => 'Google OAuth 설정',
    'section_line'                => 'LINE OAuth 설정',

    // ===== Fields & Labels =====
    'username'                    => '사용자명',
    'email'                       => '이메일',
    'password'                    => '비밀번호',
    'password_confirm'            => '비밀번호 확인',
    'remember_me'                 => '로그인 상태 유지',
    'agree_terms'                 => '이용약관에 동의합니다',
    'required_mark'               => '필수',
    'optional_mark'               => '선택',

    // ===== Buttons =====
    'login_button'                => '로그인',
    'signup_button'               => '가입하기',
    'logout_button'               => '로그아웃',
    'save_button'                 => '저장',
    'back_button'                 => '뒤로',

    // ===== Links / Helpers =====
    'have_account'                => '이미 계정이 있으신가요? 로그인',
    'no_account'                  => '계정이 없으신가요? 가입하기',
    'forgot_password'             => '비밀번호를 잊으셨나요?',

    // ===== Social Buttons =====
    'login_with_google'           => 'Google로 로그인',
    'login_with_line'             => 'LINE으로 로그인',

    // ===== Placeholders =====
    'placeholder_username'        => '예: hong_gildong',
    'placeholder_email'           => 'you@example.com',
    'placeholder_password'        => '8자 이상 권장',
    'placeholder_password_confirm'=> '비밀번호를 다시 입력',

    // ===== Success Messages =====
    'success_signup'              => '회원가입이 완료되었습니다. 자동으로 로그인되었습니다.',
    'success_login'               => '로그인되었습니다.',
    'success_logout'              => '로그아웃되었습니다.',
    'success_settings_saved'      => '설정이 저장되었습니다.',

    // ===== Generic Errors =====
    'error_required'              => '필수 항목이 입력되지 않았습니다.',
    'error_invalid_email'         => '이메일 형식이 올바르지 않습니다.',
    'error_password_short'        => '비밀번호가 너무 짧습니다(8자 이상 권장).',
    'error_password_mismatch'     => '비밀번호 확인이 일치하지 않습니다.',
    'error_username_exists'       => '이미 사용 중인 사용자명입니다.',
    'error_email_exists'          => '이미 등록된 이메일입니다.',
    'error_terms_unchecked'       => '이용약관 동의가 필요합니다.',
    'error_login_failed'          => '사용자명 또는 비밀번호가 올바르지 않습니다.',
    'error_nonce'                 => '잘못된 요청입니다(Nonce 검증 실패).',
    'error_unknown'               => '알 수 없는 오류가 발생했습니다. 잠시 후 다시 시도하세요.',

    // ===== OAuth Errors =====
    'error_oauth_generic'         => '소셜 인증 중 오류가 발생했습니다.',
    'error_oauth_state'           => '소셜 인증 상태 확인에 실패했습니다(state 불일치).',
    'error_oauth_token'           => '액세스 토큰을 가져오지 못했습니다.',
    'error_oauth_profile'         => '사용자 정보를 가져오지 못했습니다.',
    'error_oauth_email_missing'   => '이메일을 가져올 수 없습니다. 다른 로그인 방법을 시도하세요.',

    // ===== Settings Labels =====
    'google_client_id'            => 'Google Client ID',
    'google_client_secret'        => 'Google Client Secret',
    'line_client_id'              => 'LINE Channel ID',
    'line_client_secret'          => 'LINE Channel Secret',
    'redirect_url_hint'           => '각 제공자에 다음 주소를 리디렉션 URL로 등록하세요:',

    // ===== Misc =====
    'or'                          => '또는',
    'and'                         => '그리고',
    'separator'                   => '/',
    'loading'                     => '로딩 중…',
);
