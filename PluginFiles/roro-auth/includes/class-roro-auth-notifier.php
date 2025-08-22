<?php
// roro-auth/includes/class-roro-auth-notifier.php
if (!defined('ABSPATH')) { exit; }

class Roro_Auth_Notifier {
    public static function template($kind, $vars = []) {
        $v = wp_parse_args($vars, [
            'verify_url' => home_url('/?roro_verify=1'),
            'reset_url'  => home_url('/?roro_reset=1'),
            'account_id' => '',
        ]);
        switch ($kind) {
            case 'verify':
                return "以下のリンクからメール認証を完了してください:\n{$v['verify_url']}\n\n-- RORO";
            case 'reset':
                return "パスワード再設定のご案内:\n{$v['reset_url']}\n\n-- RORO";
            case 'unlink':
                return "ソーシャル連携を解除しました（ID: {$v['account_id']}）。心当たりがない場合はサポートへご連絡ください。\n\n-- RORO";
            default:
                return "通知: {$kind}";
        }
    }
}
// フィルタで件名/本文を柔軟に差し替え可能
add_filter('roro_auth_mail_subject', function($subject, $kind){
    $map = [
        'verify' => '【RORO】メールアドレスのご確認',
        'reset'  => '【RORO】パスワード再設定',
        'unlink' => '【RORO】ソーシャル連携の解除完了',
    ];
    return $map[$kind] ?? $subject;
}, 10, 2);

add_filter('roro_auth_mail_body', function($body, $kind){
    // ここでブランドトーンに合わせて文章テンプレートを上書き可能
    return $body;
}, 10, 2);
