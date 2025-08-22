<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/utils/rate_limiter.php
 *
 * レートリミッタークラス。トランジェントを用いてクライアントIP単位でアクセス回数を記録し、
 * 指定されたウィンドウ（秒数）内に許可された回数を超えると false を返します。初回アクセス時にのみ
 * TTL（有効期間）を設定し、連続アクセス時には残り時間を維持します。
 *
 * 利用例:
 *   $limiter = new \RoroCore\Utils\Rate_Limiter( 'gacha_spin', 5, HOUR_IN_SECONDS );
 *   if ( $limiter->check() ) {
 *       // 処理を実行
 *   } else {
 *       // 制限超過時の処理
 *   }
 *
 * @package RoroCore\Utils
 */

declare( strict_types = 1 );

namespace RoroCore\Utils;

class Rate_Limiter {
    /** @var string アクション名とクライアントIPを結合したトランジェントキー */
    private string $key;

    /** @var int 許可される最大アクセス回数 */
    private int $limit;

    /** @var int レート制限をリセットするまでの秒数 */
    private int $window;

    /**
     * コンストラクタ。
     *
     * @param string $action_key レート制限対象のアクション識別子（例: APIのスラッグ）。
     * @param int    $limit      許可回数。デフォルトは20回。フィルタ `roro_rate_limit` で上書き可。
     * @param int    $window     レート制限の有効期間（秒）。デフォルトは1時間。
     */
    public function __construct( string $action_key, int $limit = 20, int $window = HOUR_IN_SECONDS ) {
        // クライアントIPが取得できない場合はダミー値を使用
        $ip            = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->key     = $action_key . '_' . $ip;
        $this->limit   = (int) apply_filters( 'roro_rate_limit', $limit, $action_key );
        $this->window  = $window;
    }

    /**
     * アクセスを許可するか判定する。
     *
     * 既に上限に達している場合は false を返す。許可する場合はカウントをインクリメントし、
     * 初回アクセス時のみ有効期限を設定する。TTLを最初に設定することで、ウィンドウ中は
     * 常に残り時間が維持される。
     *
     * @return bool true=許可, false=拒否
     */
    public function check(): bool {
        $count = (int) get_transient( $this->key );
        if ( $count >= $this->limit ) {
            return false;
        }
        // 初回アクセスの場合のみTTLを設定し、それ以降はTTLを変更しない
        set_transient(
            $this->key,
            $count + 1,
            ( $count === 0 ) ? $this->window : 0
        );
        return true;
    }
}
