<?php
/**
 * フロント表示テンプレート: [roro_recommend] ショートコード用ウィジェット
 *
 * 期待するデータ:
 * - $data['messages'] : 各種表示メッセージの連想配列
 * - $data['lang']     : 現在の言語コード
 * 
 * ※JavaScript側ではグローバル変数 roroRecommend （restBase, nonce, lang, i18n）を使用
 */
if (!defined('ABSPATH')) { exit; }

$M = $data['messages'];
?>
<div class="roro-recommend-widget" data-lang="<?php echo esc_attr($data['lang']); ?>">
    <h3 class="roro-rec-title"><?php echo esc_html($M['widget_title']); ?></h3>

    <div class="roro-rec-body" aria-live="polite">
        <div class="roro-rec-loading"><?php echo esc_html($M['loading']); ?></div>
        <div class="roro-rec-error" style="display:none;"><?php echo esc_html($M['error_generic']); ?></div>
        <div class="roro-rec-empty" style="display:none;"><?php echo esc_html($M['no_recommend']); ?></div>

        <div class="roro-rec-content" style="display:none;">
            <section class="roro-rec-advice">
                <h4><?php echo esc_html($M['advice_heading']); ?></h4>
                <p class="roro-rec-advice-text"></p>
            </section>

            <section class="roro-rec-spot">
                <h4><?php echo esc_html($M['spot_heading']); ?></h4>
                <div class="roro-rec-spot-name"></div>
                <div class="roro-rec-spot-address"></div>
                <div class="roro-rec-spot-desc"></div>
            </section>
        </div>
    </div>

    <div class="roro-rec-actions" style="margin-top:12px; display:flex; gap:8px;">
        <button type="button" class="button roro-rec-refresh"><?php echo esc_html($M['btn_refresh']); ?></button>
        <button type="button" class="button roro-rec-fav"><?php echo esc_html($M['btn_favorite']); ?></button>
    </div>

    <style>
        .roro-recommend-widget { border: 1px solid #e3e3e3; padding: 12px 14px; border-radius: 6px; background: #fff; }
        .roro-rec-title { margin: 0 0 8px; font-size: 1.2em; }
        .roro-rec-advice p { margin: 4px 0; }
        .roro-rec-spot-name { font-weight: 600; margin-top: 4px; }
        .roro-rec-spot-address { color: #666; font-size: 0.95em; }
        .roro-rec-spot-desc { margin-top: 6px; }
    </style>
</div>
