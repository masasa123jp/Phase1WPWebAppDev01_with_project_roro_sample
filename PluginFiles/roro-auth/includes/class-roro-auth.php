<?php
/**
 * メインクラス（シングルトン）
 * - 管理画面機能・ログイン拡張機能の初期化を統括
 * - 依存プラグイン（roro-core）が有効な前提で動作
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Auth {

    /** @var RORO_Auth|null シングルトンインスタンス */
    private static $instance = null;

    /** @var string プラグインバージョン（表示やキャッシュバスター用途） */
    public $version;

    /** @var RORO_Auth_Admin 管理画面機能クラス */
    public $admin;

    /** @var RORO_Auth_Login ログイン拡張機能クラス */
    public $login;

    /**
     * コンストラクタ（外部から new させない）
     * - 管理画面/ログイン拡張の各機能を初期化
     */
    private function __construct() {
        $this->version = RORO_AUTH_VERSION;

        // 管理画面：設定ページの追加・保存処理・アセット読み込み
        if (is_admin()) {
            $this->admin = new RORO_Auth_Admin();
        }

        // ログイン画面拡張：OTP入力欄追加・認証処理フック
        $this->login = new RORO_Auth_Login();
    }

    /**
     * インスタンスの取得（シングルトン）
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
