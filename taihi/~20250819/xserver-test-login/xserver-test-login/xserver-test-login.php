<?php
/*
 * Plugin Name: XServer Test Login
 * Description: 統合認証プラグイン。Firebase(Google)/LINE(LIFF)によるソーシャルログインとローカル認証（ログイン・新規登録）を提供し、ログイン監査を記録します。管理画面からキー設定が可能で、簡易CRUDデモも含まれます。
 * Version: 1.5
 * Author: Project RORO
 * License: GPLv2 or later
 */

// 直アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------------
// 定数定義
// -------------------------------------------------------------------------
// プラグイン本体ファイル
define( 'XTL_PLUGIN_FILE', __FILE__ );
// プラグインディレクトリ
define( 'XTL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// プラグインURL
define( 'XTL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// ログイン画面に表示するロゴ。プラグイン内の assets/img ディレクトリに配置します。
define( 'XTL_LOGO_URL', XTL_PLUGIN_URL . 'assets/img/logo_roro.png' );
// ファビコン。ログイン画面およびフロント側に適用されます。
define( 'XTL_FAVICON_URL', XTL_PLUGIN_URL . 'assets/img/favicon.ico' );
// 管理画面用の設定オプション名
define( 'XTL_OPT_KEY', 'xtl_login_settings' );

// -------------------------------------------------------------------------
// 有効化フック：必要テーブルを作成
//  - wp_test             : CRUDデモ用
//  - wp_social_login_users : ソーシャルログインで得た外部IDを保存
//  - wp_login_audit       : 監査ログ（IP、UA、結果など）
// -------------------------------------------------------------------------
register_activation_hook( __FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // CRUDデモ用テーブル
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}test (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        datastr TEXT NOT NULL,
        PRIMARY KEY(id)
    ) $charset;";

    // ソーシャルログイン情報テーブル
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}social_login_users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider VARCHAR(50) NOT NULL,
        external_id VARCHAR(128) NOT NULL,
        email VARCHAR(191) DEFAULT NULL,
        name VARCHAR(191) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uniq_provider_external (provider, external_id)
    ) $charset;";

    // ログイン監査ログテーブル
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}login_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider VARCHAR(50) NOT NULL,
        external_id VARCHAR(128) DEFAULT NULL,
        email VARCHAR(191) DEFAULT NULL,
        result VARCHAR(20) NOT NULL,
        ip VARCHAR(64) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_provider_created (provider, created_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql1 );
    dbDelta( $sql2 );
    dbDelta( $sql3 );
} );

// -------------------------------------------------------------------------
// ログイン画面のロゴ・ファビコン設定
// -------------------------------------------------------------------------
add_action( 'login_enqueue_scripts', function () {
    // ログイン画面のロゴを差し替え。横幅・高さを拡大してロゴが小さく表示されないようにします。
    echo '<style>#login h1 a {background-image:url("' . esc_url( XTL_LOGO_URL ) . '") !important; background-size: contain !important; width: 300px !important; height: 180px !important;}</style>';
} );
// ログイン画面のロゴクリック先をサイトトップに変更
add_filter( 'login_headerurl', function () { return home_url( '/' ); } );
// ログイン画面のタイトルテキストをサイト名に変更
add_filter( 'login_headertext', function () { return get_bloginfo( 'name' ); } );
// ログイン画面およびフロント側にファビコンを挿入
add_action( 'login_head', function () {
    echo '<link rel="icon" type="image/x-icon" href="' . esc_url( XTL_FAVICON_URL ) . '">';
} );
add_action( 'wp_head', function () {
    echo '<link rel="icon" type="image/x-icon" href="' . esc_url( XTL_FAVICON_URL ) . '">';
} );

// -------------------------------------------------------------------------
// 管理画面：Firebase／LINEのキー入力ページ
// -------------------------------------------------------------------------
add_action( 'admin_menu', function () {
    add_options_page( 'RORO ログイン設定', 'RORO ログイン設定', 'manage_options', 'xtl-login-settings', 'xtl_render_settings_page' );
} );

add_action( 'admin_init', function () {
    // 設定値の保存
    register_setting( 'xtl_login_group', XTL_OPT_KEY, 'xtl_sanitize_settings' );

    // Firebase 設定
    add_settings_section( 'xtl_section_firebase', 'Firebase（Google）', '__return_false', 'xtl-login-settings' );
    add_settings_field( 'api_key',        'API Key',        'xtl_field_text',     'xtl-login-settings', 'xtl_section_firebase', array( 'key' => 'api_key' ) );
    add_settings_field( 'auth_domain',    'Auth Domain',    'xtl_field_text',     'xtl-login-settings', 'xtl_section_firebase', array( 'key' => 'auth_domain' ) );
    add_settings_field( 'project_id',     'Project ID',     'xtl_field_text',     'xtl-login-settings', 'xtl_section_firebase', array( 'key' => 'project_id' ) );
    add_settings_field( 'app_id',         'App ID',         'xtl_field_text',     'xtl-login-settings', 'xtl_section_firebase', array( 'key' => 'app_id' ) );
    add_settings_field( 'measurement_id', 'Measurement ID', 'xtl_field_text',     'xtl-login-settings', 'xtl_section_firebase', array( 'key' => 'measurement_id' ) );

    // LINE 設定
    add_settings_section( 'xtl_section_line', 'LINE（LIFF）', '__return_false', 'xtl-login-settings' );
    add_settings_field( 'line_liff_id',   'LIFF ID',      'xtl_field_text',     'xtl-login-settings', 'xtl_section_line', array( 'key' => 'line_liff_id' ) );
    add_settings_field( 'line_login_hint','未ログイン時に自動ログイン', 'xtl_field_checkbox', 'xtl-login-settings', 'xtl_section_line', array( 'key' => 'line_login_hint' ) );
} );

/**
 * 設定値のサニタイズ
 * @param array $input 入力値
 * @return array サニタイズ後の値
 */
function xtl_sanitize_settings( $input ) {
    $out  = array();
    $keys = array( 'api_key', 'auth_domain', 'project_id', 'app_id', 'measurement_id', 'line_liff_id' );
    foreach ( $keys as $k ) {
        $out[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( $input[ $k ] ) : '';
    }
    $out['line_login_hint'] = ! empty( $input['line_login_hint'] ) ? 'true' : 'false';
    return $out;
}

/**
 * デフォルト値を補完しつつ設定値を取得
 * @return array 設定値
 */
function xtl_get_settings() {
    $defaults = array(
        'api_key'        => '',
        'auth_domain'    => '',
        'project_id'     => '',
        'app_id'         => '',
        'measurement_id' => '',
        'line_liff_id'   => '',
        'line_login_hint'=> 'false',
    );
    return wp_parse_args( get_option( XTL_OPT_KEY, array() ), $defaults );
}

/**
 * テキストフィールド描画
 * @param array $args 描画設定
 */
function xtl_field_text( $args ) {
    $s = xtl_get_settings();
    $k = $args['key'];
    printf( '<input type="text" name="%s[%s]" value="%s" class="regular-text" />', esc_attr( XTL_OPT_KEY ), esc_attr( $k ), esc_attr( $s[ $k ] ?? '' ) );
}

/**
 * チェックボックス描画
 * @param array $args 描画設定
 */
function xtl_field_checkbox( $args ) {
    $s       = xtl_get_settings();
    $k       = $args['key'];
    $checked = ( $s[ $k ] ?? '' ) === 'true' ? 'checked' : '';
    printf( '<label><input type="checkbox" name="%s[%s]" value="true" %s> 有効にする</label>', esc_attr( XTL_OPT_KEY ), esc_attr( $k ), $checked );
}

/**
 * 設定ページの描画
 */
function xtl_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>RORO ログイン設定</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'xtl_login_group' );
            do_settings_sections( 'xtl-login-settings' );
            submit_button();
            ?>
        </form>
        <p>ここで設定した Firebase と LINE の値は、ショートコード <code>[roro_auth]</code> が引数を指定していない場合のデフォルトとして利用されます。</p>
    </div>
    <?php
}

// -------------------------------------------------------------------------
// CRUD デモ [test_crud_form]
// HelloWorld 表示と簡単なデータ操作フォーム
// -------------------------------------------------------------------------
add_shortcode( 'test_crud_form', function () {
    global $wpdb;
    $table = $wpdb->prefix . 'test';
    $msg   = '';

    // フォーム送信処理
    if ( ! empty( $_POST['xtl_action'] ) ) {
        if ( ! isset( $_POST['_xtl_nonce'] ) || ! wp_verify_nonce( $_POST['_xtl_nonce'], 'xtl_crud' ) ) {
            $msg = 'セキュリティチェックに失敗しました。';
        } else {
            $act = sanitize_text_field( $_POST['xtl_action'] );
            if ( 'create' === $act ) {
                $datastr = sanitize_text_field( $_POST['datastr'] ?? '' );
                if ( '' !== $datastr ) {
                    $wpdb->insert( $table, array( 'datastr' => $datastr ) );
                    $msg = '追加しました。';
                }
            } elseif ( 'update' === $act ) {
                $id      = intval( $_POST['id'] ?? 0 );
                $datastr = sanitize_text_field( $_POST['datastr'] ?? '' );
                if ( $id > 0 ) {
                    $wpdb->update( $table, array( 'datastr' => $datastr ), array( 'id' => $id ) );
                    $msg = '更新しました。';
                }
            } elseif ( 'delete' === $act ) {
                $id = intval( $_POST['id'] ?? 0 );
                if ( $id > 0 ) {
                    $wpdb->delete( $table, array( 'id' => $id ) );
                    $msg = '削除しました。';
                }
            }
        }
    }

    $rows = $wpdb->get_results( "SELECT id, datastr FROM $table ORDER BY id ASC" );
    ob_start();
    ?>
    <div class="xtl-crud">
        <h2>HelloWorld</h2>
        <?php if ( $msg ) : ?>
        <div style="color:green;margin-bottom:8px;"><?php echo esc_html( $msg ); ?></div>
        <?php endif; ?>
        <h3>新規追加</h3>
        <form method="post" style="margin-bottom:1em;">
            <?php wp_nonce_field( 'xtl_crud', '_xtl_nonce' ); ?>
            <input type="hidden" name="xtl_action" value="create" />
            <input type="text" name="datastr" required />
            <button type="submit">追加</button>
        </form>
        <h3>一覧</h3>
        <table style="border-collapse:collapse;width:100%;">
            <thead>
            <tr>
                <th style="border:1px solid #ddd;padding:6px;">ID</th>
                <th style="border:1px solid #ddd;padding:6px;">データ</th>
                <th style="border:1px solid #ddd;padding:6px;">編集</th>
                <th style="border:1px solid #ddd;padding:6px;">削除</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( $rows ) : foreach ( $rows as $r ) : ?>
                <tr>
                    <td style="border:1px solid #ddd;padding:6px;"><?php echo esc_html( $r->id ); ?></td>
                    <td style="border:1px solid #ddd;padding:6px;"><?php echo esc_html( $r->datastr ); ?></td>
                    <td style="border:1px solid #ddd;padding:6px;">
                        <form method="post">
                            <?php wp_nonce_field( 'xtl_crud', '_xtl_nonce' ); ?>
                            <input type="hidden" name="xtl_action" value="update" />
                            <input type="hidden" name="id" value="<?php echo esc_attr( $r->id ); ?>" />
                            <input type="text" name="datastr" value="<?php echo esc_attr( $r->datastr ); ?>" />
                            <button type="submit">更新</button>
                        </form>
                    </td>
                    <td style="border:1px solid #ddd;padding:6px;">
                        <form method="post" onsubmit="return confirm('削除しますか？');">
                            <?php wp_nonce_field( 'xtl_crud', '_xtl_nonce' ); ?>
                            <input type="hidden" name="xtl_action" value="delete" />
                            <input type="hidden" name="id" value="<?php echo esc_attr( $r->id ); ?>" />
                            <button type="submit">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4" style="padding:6px;">データなし</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
} );

// -------------------------------------------------------------------------
// 統一認証UI [roro_auth]
// - ローカルログインと新規登録、ソーシャルログイン(Google/LINE)を１つのカードにまとめる
// - フォーム送信時には内部でログインやユーザー作成を処理し、監査ログを記録
// - ロゴを大きく表示し、日本語の挨拶を表示
// -------------------------------------------------------------------------
add_shortcode( 'roro_auth', function () {
    // 設定値を取得（Firebase/LINE）
    $settings = xtl_get_settings();

    $errors    = array();
    // フォーム送信結果を表示するためのメッセージ
    $success_message = '';

    // -------------------------------------------------------------------
    // ローカルログイン処理
    // -------------------------------------------------------------------
    if ( ! empty( $_POST['xtl_local_login'] ) ) {
        // ノンスチェック
        if ( ! isset( $_POST['_xtl_nonce_login'] ) || ! wp_verify_nonce( $_POST['_xtl_nonce_login'], 'xtl_local_login' ) ) {
            $errors[] = 'セキュリティチェックに失敗しました。';
        } else {
            $user_login    = sanitize_text_field( $_POST['user_login'] ?? '' );
            $user_password = $_POST['user_pass'] ?? '';
            $remember      = ! empty( $_POST['remember'] );
            $creds         = array(
                'user_login'    => $user_login,
                'user_password' => $user_password,
                'remember'      => $remember,
            );
            $user = wp_signon( $creds, is_ssl() );
            if ( is_wp_error( $user ) ) {
                $errors[] = 'ユーザー名またはパスワードが正しくありません。';
                xtl_write_audit( 'local-login', null, $user_login, 'fail' );
            } else {
                xtl_write_audit( 'local-login', (string) $user->ID, $user->user_email, 'success' );
                wp_safe_redirect( home_url( '/' ) );
                exit;
            }
        }
    }

    // -------------------------------------------------------------------
    // 新規登録処理
    // -------------------------------------------------------------------
    if ( ! empty( $_POST['xtl_local_register'] ) ) {
        // ノンスチェック
        if ( ! isset( $_POST['_xtl_nonce_reg'] ) || ! wp_verify_nonce( $_POST['_xtl_nonce_reg'], 'xtl_local_register' ) ) {
            $errors[] = 'セキュリティチェックに失敗しました。';
        } else {
            $user_login = sanitize_user( $_POST['user_login'] ?? '' );
            $email      = sanitize_email( $_POST['user_email'] ?? '' );
            $pass1      = (string) ( $_POST['user_pass1'] ?? '' );
            $pass2      = (string) ( $_POST['user_pass2'] ?? '' );
            $agree      = ! empty( $_POST['agree_terms'] );

            if ( '' === $user_login ) {
                $errors[] = 'ユーザー名を入力してください。';
            }
            if ( '' === $email || ! is_email( $email ) ) {
                $errors[] = '有効なメールアドレスを入力してください。';
            }
            if ( strlen( $pass1 ) < 8 ) {
                $errors[] = 'パスワードは8文字以上にしてください。';
            }
            if ( $pass1 !== $pass2 ) {
                $errors[] = 'パスワードが一致しません。';
            }
            if ( ! $agree ) {
                $errors[] = '利用規約に同意してください。';
            }
            if ( username_exists( $user_login ) ) {
                $errors[] = 'そのユーザー名は既に使われています。';
            }
            if ( email_exists( $email ) ) {
                $errors[] = 'そのメールアドレスは既に登録済みです。';
            }

            if ( empty( $errors ) ) {
                $uid = wp_create_user( $user_login, $pass1, $email );
                if ( is_wp_error( $uid ) ) {
                    $errors[] = 'ユーザー作成に失敗しました: ' . $uid->get_error_message();
                    xtl_write_audit( 'local-register', null, $email, 'fail' );
                } else {
                    xtl_write_audit( 'local-register', (string) $uid, $email, 'success' );
                    // 自動ログイン
                    wp_set_current_user( $uid );
                    wp_set_auth_cookie( $uid, true );
                    wp_safe_redirect( home_url( '/' ) );
                    exit;
                }
            }
        }
    }

    // -------------------------------------------------------------------
    // ペット登録処理
    // - 「こちらから新規登録」リンクからアクセスした専用フォーム
    // - 今回はデータベース保存やユーザー作成までは行わず、必須項目のチェックと完了メッセージのみを表示します。
    // -------------------------------------------------------------------
    if ( ! empty( $_POST['xtl_pet_register'] ) ) {
        // ノンスチェック
        if ( ! isset( $_POST['_xtl_nonce_pet'] ) || ! wp_verify_nonce( $_POST['_xtl_nonce_pet'], 'xtl_pet_register' ) ) {
            $errors[] = 'セキュリティチェックに失敗しました。';
        } else {
            // 必須項目チェック
            $pet_name   = sanitize_text_field( $_POST['pet_name'] ?? '' );
            $furigana   = sanitize_text_field( $_POST['furigana'] ?? '' );
            $email_pet  = sanitize_email( $_POST['email_pet'] ?? '' );
            $pet_type   = sanitize_text_field( $_POST['pet_type'] ?? '' );
            $pet_age    = sanitize_text_field( $_POST['pet_age'] ?? '' );
            $language   = sanitize_text_field( $_POST['language'] ?? '' );
            $agree_priv = ! empty( $_POST['privacy_policy'] );
            if ( '' === $pet_name ) {
                $errors[] = 'ペットの名前を入力してください。';
            }
            if ( '' === $furigana ) {
                $errors[] = 'フリガナを入力してください。';
            }
            if ( '' === $email_pet || ! is_email( $email_pet ) ) {
                $errors[] = '有効なメールアドレスを入力してください。';
            }
            if ( '' === $pet_type ) {
                $errors[] = '犬か猫かを選択してください。';
            }
            if ( '' === $pet_age ) {
                $errors[] = 'ペットの年齢を選択してください。';
            }
            if ( '' === $language ) {
                $errors[] = '言語を選択してください。';
            }
            if ( ! $agree_priv ) {
                $errors[] = 'プライバシーポリシーに同意してください。';
            }
            // エラーがなければ成功メッセージを設定
            if ( empty( $errors ) ) {
                $success_message = '登録が完了しました。内容を確認の上、引き続きご利用ください。';
            }
        }
    }

    // HTML出力開始
    ob_start();
    ?>
    <style>
      /* 全体レイアウト */
      .roro-auth-wrapper{max-width:480px;margin:40px auto;padding:0 16px;text-align:center;font-family: sans-serif;}
      .roro-auth-wrapper img.roro-logo{width:200px;height:auto;margin:0 auto 24px;}
      .roro-greeting{font-size:1.6rem;font-weight:bold;margin-bottom:24px;display:flex;align-items:center;justify-content:center;gap:8px;}
      .roro-greeting span.emoji{font-size:1.6rem;}
      .roro-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:12px;padding:10px;margin-bottom:16px;display:block;text-align:left;}
      .roro-form{margin-bottom:24px;text-align:left;}
      .roro-form label{font-weight:600;font-size:0.95rem;display:block;margin-bottom:4px;}
      .roro-input{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:10px;margin-bottom:16px;font-size:1rem;}
      .roro-btn{display:block;width:100%;padding:12px;border-radius:10px;font-weight:700;margin-bottom:12px;cursor:pointer;font-size:1rem;}
      .roro-btn.login{background:#F9C846;color:#333;border:none;}
      .roro-btn.google{background:#fff;color:#4285F4;border:1px solid #4285F4;}
      .roro-btn.line{background:#00b900;color:#fff;border:none;}
      .roro-link{text-align:center;font-size:0.9rem;margin-top:12px;}
      .roro-link a{color:#0066cc;text-decoration:underline;}
      .roro-success{background:#e6ffed;color:#064420;border:1px solid #bbf7d0;border-radius:12px;padding:10px;margin-bottom:16px;text-align:left;}
      /* ステップインジケータ */
      .roro-steps{display:flex;justify-content:center;gap:8px;margin-bottom:20px;}
      .roro-steps .step{display:flex;align-items:center;gap:6px;font-size:0.85rem;color:#9ca3af;}
      .roro-steps .step .circle{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e5e7eb;color:#6b7280;font-weight:700;}
      .roro-steps .step.current .circle{background:#3b82f6;color:#fff;}
      .roro-steps .step.current .label{color:#3b82f6;font-weight:700;}
    </style>
    <div class="roro-auth-wrapper">
      <!-- ロゴ -->
      <img class="roro-logo" src="<?php echo esc_url( XTL_LOGO_URL ); ?>" alt="Project RORO" />
      <!-- 挨拶 -->
      <div class="roro-greeting">こんにちは！<span class="emoji">🌐</span></div>
      <!-- エラー表示 -->
      <?php if ( ! empty( $errors ) ) : ?>
        <div class="roro-error" role="alert">
          <?php echo implode( "<br/>", array_map( 'esc_html', $errors ) ); ?>
        </div>
      <?php endif; ?>
      <!-- 成功メッセージ表示 -->
      <?php if ( ! empty( $success_message ) ) : ?>
        <div class="roro-success" role="status">
          <?php echo esc_html( $success_message ); ?>
        </div>
      <?php endif; ?>
      <!-- ログインフォーム -->
      <form id="roro-login" class="roro-form" method="post" <?php echo ! empty( $_POST['xtl_local_register'] ) ? 'style="display:none;"' : ''; ?>>
        <?php wp_nonce_field( 'xtl_local_login', '_xtl_nonce_login' ); ?>
        <input type="hidden" name="xtl_local_login" value="1" />
        <label for="roro-login-username">メールアドレス</label>
        <input id="roro-login-username" class="roro-input" type="text" name="user_login" autocomplete="username" required />
        <label for="roro-login-pass">パスワード</label>
        <input id="roro-login-pass" class="roro-input" type="password" name="user_pass" autocomplete="current-password" required />
        <div class="roro-btn login" role="button" onclick="document.getElementById('roro-login').submit();">ログイン</div>
        <!-- ソーシャルボタン -->
        <div class="roro-btn google" id="roro-google">Googleでログイン</div>
        <div class="roro-btn line" id="roro-line">LINEでログイン</div>
        <!-- 新規登録へのリンク -->
        <div class="roro-link">アカウントをお持ちでない場合は <a href="#" id="show-pet-register">こちらから新規登録</a></div>
      </form>
      <!-- 新規登録フォーム（既存:ユーザー名/パスワード） -->
      <form id="roro-signup" class="roro-form" method="post" <?php echo ! empty( $_POST['xtl_local_register'] ) ? '' : 'style="display:none;"'; ?>>
        <?php wp_nonce_field( 'xtl_local_register', '_xtl_nonce_reg' ); ?>
        <input type="hidden" name="xtl_local_register" value="1" />
        <label for="roro-signup-username">ユーザー名</label>
        <input id="roro-signup-username" class="roro-input" type="text" name="user_login" autocomplete="username" required />
        <label for="roro-signup-email">メールアドレス</label>
        <input id="roro-signup-email" class="roro-input" type="email" name="user_email" autocomplete="email" required />
        <label for="roro-signup-pass1">パスワード</label>
        <input id="roro-signup-pass1" class="roro-input" type="password" name="user_pass1" autocomplete="new-password" minlength="8" required />
        <label for="roro-signup-pass2">パスワード（確認）</label>
        <input id="roro-signup-pass2" class="roro-input" type="password" name="user_pass2" autocomplete="new-password" minlength="8" required />
        <label style="display:block;margin:8px 0;">
          <input type="checkbox" name="agree_terms" value="1" required /> 利用規約とプライバシーポリシーに同意します
        </label>
        <div class="roro-btn login" role="button" onclick="document.getElementById('roro-signup').submit();">登録してはじめる</div>
        <!-- 切り替え -->
        <div class="roro-link">既にアカウントをお持ちの方は <a href="#" id="show-login">こちら</a></div>
      </form>

      <!-- 新規登録フォーム（ペット情報） -->
      <form id="roro-pet-register" class="roro-form" method="post" style="display:none;">
        <?php wp_nonce_field( 'xtl_pet_register', '_xtl_nonce_pet' ); ?>
        <input type="hidden" name="xtl_pet_register" value="1" />
        <!-- ステップインジケータ：現在は基本情報を入力するフェーズを強調します -->
        <div class="roro-steps">
          <div class="step current"><span class="circle">1</span><span class="label">基本情報</span></div>
          <div class="step"><span class="circle">2</span><span class="label">住所情報</span></div>
          <div class="step"><span class="circle">3</span><span class="label">登録完了</span></div>
        </div>
        <!-- セクション: 基本情報 -->
        <h3 style="font-size:1.2rem;font-weight:700;margin-top:0;margin-bottom:12px;">基本情報</h3>
        <label for="pet_name">ペットの名前</label>
        <input id="pet_name" class="roro-input" type="text" name="pet_name" autocomplete="off" placeholder="例: ぽち" required />
        <label for="furigana">フリガナ</label>
        <input id="furigana" class="roro-input" type="text" name="furigana" autocomplete="off" placeholder="例: ポチ" required />
        <label for="phone">電話番号</label>
        <input id="phone" class="roro-input" type="tel" name="phone" autocomplete="tel" placeholder="ハイフン無し" />
        <label for="email_pet">メールアドレス</label>
        <input id="email_pet" class="roro-input" type="email" name="email_pet" autocomplete="email" placeholder="example@example.com" required />
        <!-- セクション: 住所 -->
        <h3 style="font-size:1.2rem;font-weight:700;margin-top:24px;margin-bottom:12px;">住所情報</h3>
        <label for="postal_code">郵便番号</label>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
          <input id="postal_code" class="roro-input" type="text" name="postal_code" style="flex:1" autocomplete="postal-code" placeholder="例: 1070052" />
          <button type="button" class="roro-btn" style="flex:none;width:100px;background:#f3f4f6;color:#333;border:1px solid #d1d5db;" onclick="/* TODO: 住所自動入力機能 */">自動入力</button>
        </div>
        <label for="prefecture">都道府県</label>
        <input id="prefecture" class="roro-input" type="text" name="prefecture" autocomplete="address-level1" placeholder="例: 東京都" />
        <label for="city">市区町村</label>
        <input id="city" class="roro-input" type="text" name="city" autocomplete="address-level2" placeholder="例: 港区" />
        <label for="street">番地/建物名</label>
        <input id="street" class="roro-input" type="text" name="street" autocomplete="street-address" placeholder="例: 8丁目1-22 青山一丁目プレイス" />
        <label for="notes">その他ご要望を追加する</label>
        <textarea id="notes" class="roro-input" name="notes" rows="3" style="resize:vertical;" placeholder="自由記入欄"></textarea>
        <!-- セクション: ペット情報 -->
        <h3 style="font-size:1.2rem;font-weight:700;margin-top:24px;margin-bottom:12px;">ペット情報</h3>
        <label for="pet_type">犬 or 猫</label>
        <select id="pet_type" class="roro-input" name="pet_type" required>
          <option value="" disabled selected>選択してください</option>
          <option value="dog">犬</option>
          <option value="cat">猫</option>
        </select>
        <!-- 犬種情報 (犬の場合のみ) -->
        <div id="dog-options" style="display:none;">
          <label for="breed">犬種情報</label>
          <select id="breed" class="roro-input" name="breed">
            <option value="柴犬">柴犬</option>
            <option value="ラブラドール">ラブラドール</option>
            <option value="チワワ">チワワ</option>
            <option value="ダックスフンド">ダックスフンド</option>
            <option value="ポメラニアン">ポメラニアン</option>
          </select>
        </div>
        <label for="pet_age">ペットの年齢</label>
        <select id="pet_age" class="roro-input" name="pet_age" required>
          <option value="" disabled selected>選択してください</option>
          <option value="puppy">子犬/子猫（1歳未満）</option>
          <option value="adult">成犬/成猫（1〜7歳）</option>
          <option value="senior">シニア犬/シニア猫（7歳以上）</option>
        </select>
        <!-- 言語選択 -->
        <label for="language">言語</label>
        <select id="language" class="roro-input" name="language" required>
          <option value="" disabled selected>選択してください</option>
          <option value="ja">日本語</option>
          <option value="en">英語</option>
          <option value="zh">中国語</option>
          <option value="ko">韓国語</option>
        </select>
        <!-- プライバシーポリシー同意 -->
        <label style="display:block;margin:16px 0;">
          <input type="checkbox" name="privacy_policy" value="1" required /> プライバシーポリシーに同意する
        </label>
        <!-- 送信ボタン -->
        <div class="roro-btn login" role="button" onclick="document.getElementById('roro-pet-register').submit();">登録する</div>
        <!-- 戻るリンク -->
        <div class="roro-link">戻る → <a href="#" id="show-login-from-pet">ログイン</a></div>
      </form>
    </div>
    <script>
    (function(){
      // フォーム切り替え
      var showPetRegister = document.getElementById('show-pet-register');
      var showLogin  = document.getElementById('show-login');
      var showLoginFromPet = document.getElementById('show-login-from-pet');
      var loginForm  = document.getElementById('roro-login');
      var signupForm = document.getElementById('roro-signup');
      var petForm    = document.getElementById('roro-pet-register');
      // 切り替え: ログイン→新規登録（ペット情報）
      if (showPetRegister) showPetRegister.addEventListener('click', function(e){ e.preventDefault(); loginForm.style.display='none'; signupForm.style.display='none'; if(petForm) petForm.style.display='block'; });
      // 切り替え: 新規登録（旧ユーザー用）→ログイン
      if (showLogin)  showLogin.addEventListener('click', function(e){ e.preventDefault(); signupForm.style.display='none'; if(petForm) petForm.style.display='none'; loginForm.style.display='block'; });
      // 切り替え: ペット登録→ログイン
      if (showLoginFromPet) showLoginFromPet.addEventListener('click', function(e){ e.preventDefault(); if(petForm) petForm.style.display='none'; signupForm.style.display='none'; loginForm.style.display='block'; });
      // パスワード強度（新規登録）
      var pass1 = document.getElementById('roro-signup-pass1');
      // ソーシャルログイン保存関数
      function save(provider, uid, name, email, result){
        var body = new URLSearchParams();
        body.append('action','xtl_social_store');
        body.append('provider', provider);
        body.append('uid', uid || '');
        body.append('name', name || '');
        body.append('email', email || '');
        body.append('result', result || 'success');
        return fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString()
        });
      }
      // Firebase 初期化
      var s = <?php echo wp_json_encode( $settings ); ?>;
      try {
        if (s.api_key) {
          firebase.initializeApp({apiKey:s.api_key, authDomain:s.auth_domain, projectId:s.project_id, appId:s.app_id, measurementId:s.measurement_id});
        }
      } catch(e){}
      // Google
      var gbtn = document.getElementById('roro-google');
      if (gbtn) gbtn.addEventListener('click', function(){
        if (!firebase.apps.length) { alert('Firebaseが未設定です。設定画面で登録してください。'); return; }
        var prov = new firebase.auth.GoogleAuthProvider();
        firebase.auth().signInWithPopup(prov).then(function(res){
          var u = res.user || {};
          save('google', u.uid, u.displayName, u.email, 'success').then(function(){location.reload();});
        }).catch(function(err){ alert('Googleログインに失敗しました。'); save('google','','','', 'fail'); });
      });
      // LINE
      var lbtn = document.getElementById('roro-line');
      if (lbtn) {
        // LIFF 初期化
        var liffId = s.line_liff_id;
        var auto   = (s.line_login_hint === 'true');
        if (liffId) {
          liff.init({ liffId: liffId }).then(function(){ if (auto && !liff.isLoggedIn()) liff.login(); }).catch(function(e){});
        }
        lbtn.addEventListener('click', function(){
          if (!liffId) { alert('LINEのLIFF IDが設定されていません。設定画面で登録してください。'); return; }
          if (!liff.isLoggedIn()) { liff.login(); return; }
          Promise.all([liff.getProfile(), liff.getDecodedIDToken()]).then(function(res){
            var pf = res[0] || {}; var tok = res[1] || {};
            save('line', pf.userId || '', pf.displayName || '', tok.email || '', 'success').then(function(){ location.reload(); });
          }).catch(function(err){ alert('LINEログイン情報の取得に失敗しました。'); save('line','','','', 'fail'); });
        });
      }

      // ペット種別による犬種表示切替
      var petType = document.getElementById('pet_type');
      var dogOpts = document.getElementById('dog-options');
      if (petType && dogOpts) {
        function toggleBreed() {
          if (petType.value === 'dog') {
            dogOpts.style.display = '';
          } else {
            dogOpts.style.display = 'none';
          }
        }
        petType.addEventListener('change', toggleBreed);
        toggleBreed();
      }
    })();
    </script>
    <?php
    return ob_get_clean();
} );

// -------------------------------------------------------------------------
// 旧形式ショートコード：ローカルログインフォーム [local_login_form]
// - バックワードコンパチのため残していますが、新UIの利用を推奨します。
// -------------------------------------------------------------------------
add_shortcode( 'local_login_form', function () {
    // ここでは roro_auth に委譲します
    return do_shortcode( '[roro_auth]' );
} );

// -------------------------------------------------------------------------
// 旧形式ショートコード：新規登録フォーム [local_register_form]
// - バックワードコンパチのため残していますが、新UIの利用を推奨します。
// -------------------------------------------------------------------------
add_shortcode( 'local_register_form', function () {
    // ここでは roro_auth に委譲します
    return do_shortcode( '[roro_auth]' );
} );

// -------------------------------------------------------------------------
// AJAX：ソーシャルログインユーザー保存 + 監査記録
// -------------------------------------------------------------------------
add_action( 'wp_ajax_nopriv_xtl_social_store', 'xtl_social_store' );
add_action( 'wp_ajax_xtl_social_store', 'xtl_social_store' );
function xtl_social_store() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        wp_die();
    }
    global $wpdb;
    $provider = sanitize_text_field( $_POST['provider'] ?? '' );
    $uid      = sanitize_text_field( $_POST['uid'] ?? '' );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );
    $email    = sanitize_email( $_POST['email'] ?? '' );
    $result   = sanitize_text_field( $_POST['result'] ?? 'success' );

    if ( ! $provider ) {
        echo '必要情報不足';
        xtl_write_audit( 'unknown', null, null, 'fail' );
        wp_die();
    }

    // ソーシャルユーザー保存（uid がある場合のみ）
    if ( $uid ) {
        $table = $wpdb->prefix . 'social_login_users';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE provider = %s AND external_id = %s", $provider, $uid ) );
        if ( $exists ) {
            $wpdb->update( $table, array( 'email' => $email, 'name' => $name ), array( 'id' => $exists ) );
        } else {
            $wpdb->insert( $table, array( 'provider' => $provider, 'external_id' => $uid, 'email' => $email, 'name' => $name, 'created_at' => current_time( 'mysql', 1 ) ) );
        }
    }

    // 監査ログ記録
    xtl_write_audit( $provider, $uid ?: null, $email ?: null, in_array( $result, array( 'success', 'fail' ), true ) ? $result : 'success' );

    echo esc_html( $name ?: 'ユーザー' ) . ' でログインしました。';
    wp_die();
}

/**
 * 監査ログの書き込み
 * @param string $provider プロバイダ名
 * @param string|null $external_id 外部ID
 * @param string|null $email メール
 * @param string $result 結果 (success/fail)
 */
function xtl_write_audit( $provider, $external_id, $email, $result ) {
    global $wpdb;
    $table = $wpdb->prefix . 'login_audit';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $wpdb->insert( $table, array(
        'provider'    => sanitize_text_field( $provider ?: 'unknown' ),
        'external_id' => $external_id ? sanitize_text_field( $external_id ) : null,
        'email'       => $email ? sanitize_email( $email ) : null,
        'result'      => in_array( $result, array( 'success', 'fail' ), true ) ? $result : 'success',
        'ip'          => sanitize_text_field( $ip ),
        'user_agent'  => $ua,
        'created_at'  => current_time( 'mysql', 1 ),
    ) );
}