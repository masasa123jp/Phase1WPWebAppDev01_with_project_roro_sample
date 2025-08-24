<?php
/**
 * [roro_favorites] 用テンプレート
 */
if (!defined('ABSPATH')) { exit; }
$M = $data['messages'];
?>
<div class="roro-fav-container" data-lang="<?php echo esc_attr($data['lang']); ?>">
    <?php if (isset($_GET['roro_fav_status'])): ?>
        <?php
        $s = sanitize_text_field($_GET['roro_fav_status']);
        $msg = '';
        if ($s === 'added') {
            $msg = $M['notice_added'];
        } elseif ($s === 'duplicate') {
            $msg = $M['notice_duplicate'];
        } elseif ($s === 'removed') {
            $msg = $M['notice_removed'];
        } elseif ($s === 'login') {
            $msg = $M['must_login'];
        } elseif ($s === 'error') {
            $msg = $M['notice_error'];
        }
        ?>
        <?php if ($msg): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
        <?php endif; ?>
    <?php endif; ?>

    <h3 class="roro-fav-title"><?php echo esc_html($M['fav_title']); ?></h3>
    <div class="roro-fav-empty" style="display:none;"><?php echo esc_html($M['empty']); ?></div>
    <ul class="roro-fav-list"></ul>
</div>
