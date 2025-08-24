<?php
/**
 * RORO Auth - English messages
 */
$roro_auth_messages = array(
    // ===== UI Titles =====
    'login_title'                 => 'Sign in',
    'signup_title'                => 'Create an account',
    'social_login_title'          => 'Sign in with Social',
    'settings_title'              => 'RORO Auth Settings',
    'section_google'              => 'Google OAuth Settings',
    'section_line'                => 'LINE OAuth Settings',

    // ===== Fields & Labels =====
    'username'                    => 'Username',
    'email'                       => 'Email',
    'password'                    => 'Password',
    'password_confirm'            => 'Confirm Password',
    'remember_me'                 => 'Remember me',
    'agree_terms'                 => 'I agree to the Terms of Service',
    'required_mark'               => 'Required',
    'optional_mark'               => 'Optional',

    // ===== Buttons =====
    'login_button'                => 'Sign in',
    'signup_button'               => 'Sign up',
    'logout_button'               => 'Sign out',
    'save_button'                 => 'Save',
    'back_button'                 => 'Back',

    // ===== Links / Helpers =====
    'have_account'                => 'Already have an account? Sign in',
    'no_account'                  => 'Don’t have an account? Sign up',
    'forgot_password'             => 'Forgot your password?',

    // ===== Social Buttons =====
    'login_with_google'           => 'Sign in with Google',
    'login_with_line'             => 'Sign in with LINE',

    // ===== Placeholders =====
    'placeholder_username'        => 'e.g. john_doe',
    'placeholder_email'           => 'you@example.com',
    'placeholder_password'        => 'At least 8 characters recommended',
    'placeholder_password_confirm'=> 'Enter the same password again',

    // ===== Success Messages =====
    'success_signup'              => 'Your account has been created and you are now signed in.',
    'success_login'               => 'Signed in successfully.',
    'success_logout'              => 'You have been signed out.',
    'success_settings_saved'      => 'Settings have been saved.',

    // ===== Generic Errors =====
    'error_required'              => 'Required fields are missing.',
    'error_invalid_email'         => 'Invalid email format.',
    'error_password_short'        => 'Password is too short (8+ characters recommended).',
    'error_password_mismatch'     => 'Password confirmation does not match.',
    'error_username_exists'       => 'This username is already taken.',
    'error_email_exists'          => 'This email address is already registered.',
    'error_terms_unchecked'       => 'You must agree to the Terms of Service.',
    'error_login_failed'          => 'Incorrect username or password.',
    'error_nonce'                 => 'Invalid request (nonce verification failed).',
    'error_unknown'               => 'An unknown error occurred. Please try again later.',

    // ===== OAuth Errors =====
    'error_oauth_generic'         => 'An error occurred during social authentication.',
    'error_oauth_state'           => 'State verification failed for social authentication.',
    'error_oauth_token'           => 'Failed to obtain an access token.',
    'error_oauth_profile'         => 'Failed to fetch user profile.',
    'error_oauth_email_missing'   => 'Could not retrieve your email address. Please try another method.',

    // ===== Settings Labels =====
    'google_client_id'            => 'Google Client ID',
    'google_client_secret'        => 'Google Client Secret',
    'line_client_id'              => 'LINE Channel ID',
    'line_client_secret'          => 'LINE Channel Secret',
    'redirect_url_hint'           => 'Register the following as redirect URL for each provider:',

    // ===== Misc =====
    'or'                          => 'or',
    'and'                         => 'and',
    'separator'                   => '/',
    'loading'                     => 'Loading…',
);
