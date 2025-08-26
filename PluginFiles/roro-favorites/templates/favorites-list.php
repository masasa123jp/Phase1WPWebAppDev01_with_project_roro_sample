<?php
/**
 * お気に入り一覧テンプレート。
 *
 * このテンプレートはログイン中ユーザーのお気に入りをリスト形式で表示します。
 * サービスクラスを通じてデータと翻訳を取得し、フロントエンドのスクリプトは
 * 短縮コード側で読み込まれている前提です。
 *
 * @package RORO_Favorites
 */
defined('ABSPATH') || exit;

// 現在のユーザーがログインしていなければ表示しない
if (!is_user_logged_in()) {
    return;
}

// サービス取得
$svc = new RORO_Favorites_Service();
$lang = $svc->detect_lang();
$messages = $svc->load_lang($lang);

// お気に入りリスト取得
$list = $svc->list_favorites(get_current_user_id(), $lang);

?>
<div class="roro-fav">
    <h2><?php echo esc_html($messages['fav_title'] ?? __('Favorites', 'roro-favorites')); ?></h2>
    <?php if (empty($list)): ?>
        <p><?php echo esc_html($messages['empty'] ?? __('No favorites yet.', 'roro-favorites')); ?></p>
    <?php else: ?>
        <ul class="roro-fav-list" style="list-style:none;padding:0;margin:0;">
            <?php foreach ($list as $row):
                $type = esc_attr($row['target_type']);
                $id   = (int) $row['target_id'];
                $name = $row['name'];
                $address = $row['address'] ?? '';
                $description = $row['description'] ?? '';
                // マップハイライト用のリンク
                $map = add_query_arg(['highlight_' . $type => $id], home_url('/map/'));
            ?>
            <li class="roro-fav-item" data-target="<?php echo $type; ?>" data-id="<?php echo $id; ?>" style="border:1px solid #eee;border-radius:10px;padding:12px;margin:10px 0;">
                <div class="roro-fav-row" style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <div class="roro-fav-col">
                        <span style="font-size:12px;color:#666;">
                            <?php echo esc_html(strtoupper($type)); ?>
                        </span>
                        <h3 style="margin:.25rem 0 0 0;">
                            <?php echo esc_html($name); ?>
                        </h3>
                        <?php if (!empty($address)): ?>
                            <p class="roro-fav-address" style="margin:0;"><small><?php echo esc_html($address); ?></small></p>
                        <?php endif; ?>
                        <?php if (!empty($description)): ?>
                            <p class="roro-fav-desc" style="margin:0;"><small><?php echo esc_html($description); ?></small></p>
                        <?php endif; ?>
                    </div>
                    <div class="roro-fav-ops" style="display:flex;gap:8px;align-items:center;">
                        <a class="button roro-fav-map" href="<?php echo esc_url($map); ?>">
                            🗺 <?php echo esc_html($messages['openmap'] ?? __('Open on Map', 'roro-favorites')); ?>
                        </a>
                        <button type="button" class="roro-fav-toggle button button-secondary" aria-pressed="true" title="<?php echo esc_attr($messages['btn_remove'] ?? __('Remove', 'roro-favorites')); ?>">★</button>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div id="roro-fav-toast" class="roro-fav-toast" style="position:fixed;right:16px;bottom:16px;display:none;background:#111;color:#fff;padding:8px 12px;border-radius:8px;"></div>
</div>