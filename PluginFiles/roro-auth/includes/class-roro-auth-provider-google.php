<?php
if (!defined('ABSPATH')) exit;

class RORO_Auth_Provider_Google {
    const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    const USERINFO       = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function authorize_url($state) {
        $opt = RORO_Auth_Utils::get_settings();
        $client_id = $opt['google_client_id'];
        $redirect  = RORO_Auth_Utils::redirect_uri('google');
        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'consent', // 確実にemail取得
        ];
        return add_query_arg($params, self::AUTH_ENDPOINT);
    }

    /** @return array|WP_Error profile配列 or エラー */
    public static function exchange_and_profile($code) {
        $opt = RORO_Auth_Utils::get_settings();
        $body = [
            'code'          => $code,
            'client_id'     => $opt['google_client_id'],
            'client_secret' => $opt['google_client_secret'],
            'redirect_uri'  => RORO_Auth_Utils::redirect_uri('google'),
            'grant_type'    => 'authorization_code',
        ];
        $res = wp_remote_post(self::TOKEN_ENDPOINT, ['timeout' => 15, 'body' => $body]);
        if (is_wp_error($res)) return $res;
        $code_http = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code_http !== 200 || !isset($json['access_token'])) {
            return new WP_Error('google_token_error', 'Failed to exchange token');
        }
        $access_token = $json['access_token'];
        $id_token     = $json['id_token'] ?? '';

        // ユーザ情報
        $u = wp_remote_get(self::USERINFO, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 15,
        ]);
        if (is_wp_error($u)) return $u;
        $code_http = wp_remote_retrieve_response_code($u);
        $info = json_decode(wp_remote_retrieve_body($u), true);
        if ($code_http !== 200 || !isset($info['sub'])) {
            return new WP_Error('google_userinfo_error', 'Failed to fetch userinfo');
        }
        // 整形
        $profile = [
            'sub'            => $info['sub'],
            'email'          => $info['email'] ?? '',
            'email_verified' => !empty($info['email_verified']),
            'name'           => $info['name'] ?? ($info['given_name'] ?? ''),
            'picture'        => $info['picture'] ?? '',
            'locale'         => $info['locale'] ?? '',
        ];
        return $profile;
    }
}
