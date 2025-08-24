<?php
if (!defined('ABSPATH')) exit;

class RORO_Auth_Provider_LINE {
    const AUTH_ENDPOINT  = 'https://access.line.me/oauth2/v2.1/authorize';
    const TOKEN_ENDPOINT = 'https://api.line.me/oauth2/v2.1/token';
    const PROFILE        = 'https://api.line.me/v2/profile';

    public static function authorize_url($state) {
        $opt = RORO_Auth_Utils::get_settings();
        $client_id = $opt['line_channel_id'];
        $redirect  = RORO_Auth_Utils::redirect_uri('line');
        $params = [
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect,
            'state'         => $state,
            'scope'         => 'openid profile email',
            'prompt'        => 'consent',
        ];
        return add_query_arg($params, self::AUTH_ENDPOINT);
    }

    /** @return array|WP_Error profile配列 or エラー */
    public static function exchange_and_profile($code) {
        $opt = RORO_Auth_Utils::get_settings();
        $args = [
            'timeout' => 15,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => RORO_Auth_Utils::redirect_uri('line'),
                'client_id'     => $opt['line_channel_id'],
                'client_secret' => $opt['line_channel_secret'],
            ],
        ];
        $res = wp_remote_post(self::TOKEN_ENDPOINT, $args);
        if (is_wp_error($res)) return $res;
        $code_http = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code_http !== 200 || empty($json['access_token'])) {
            return new WP_Error('line_token_error', 'Failed to exchange token');
        }
        $access_token = $json['access_token'];
        $id_token     = $json['id_token'] ?? '';

        // プロフィール（displayName/userId/pictureUrl）
        $u = wp_remote_get(self::PROFILE, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 15,
        ]);
        if (is_wp_error($u)) return $u;
        $code_http = wp_remote_retrieve_response_code($u);
        $info = json_decode(wp_remote_retrieve_body($u), true);
        if ($code_http !== 200 || empty($info['userId'])) {
            return new WP_Error('line_profile_error', 'Failed to fetch profile');
        }

        // id_token から email を抽出（email スコープ許諾時）
        $email = '';
        $email_verified = false;
        if ($id_token) {
            $payload = RORO_Auth_Utils::jwt_decode_payload($id_token);
            if (is_array($payload)) {
                $email = $payload['email'] ?? '';
                $email_verified = !empty($payload['email_verified']);
            }
        }

        $profile = [
            'sub'            => $info['userId'],
            'email'          => $email,
            'email_verified' => $email ? $email_verified : false,
            'name'           => $info['displayName'] ?? '',
            'picture'        => $info['pictureUrl'] ?? '',
            'locale'         => '',
        ];
        return $profile;
    }
}
