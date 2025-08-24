<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Mag_Admin {

    public function register_meta_boxes() {
        add_meta_box('roro_mag_issue_box', __('Issue Settings','roro-magazine'), [$this, 'issue_box'], 'roro_mag_issue', 'normal', 'high');
        add_meta_box('roro_mag_article_box', __('Article Settings','roro-magazine'), [$this, 'article_box'], 'roro_mag_article', 'normal', 'high');
    }

    public function issue_box($post) {
        wp_nonce_field('roro_mag_issue_box', 'roro_mag_issue_nonce');
        $svc = new RORO_Mag_Service();
        $langs = ['ja','en','zh','ko'];
        $issue_key = get_post_meta($post->ID, '_roro_mag_issue_key', true);
        ?>
        <style>.roro-mag-grid{display:grid;grid-template-columns:140px 1fr;gap:8px;}</style>
        <div class="roro-mag-grid">
            <label><strong><?php _e('Issue Key (YYYY-MM)','roro-magazine'); ?></strong></label>
            <input type="text" name="roro_mag_issue_key" value="<?php echo esc_attr($issue_key); ?>" placeholder="2025-06" />
            <?php foreach($langs as $l): 
                $t = get_post_meta($post->ID, "_roro_mag_title_{$l}", true);
                $s = get_post_meta($post->ID, "_roro_mag_summary_{$l}", true);
            ?>
                <label><strong><?php echo esc_html(strtoupper($l)); ?> <?php _e('Title','roro-magazine'); ?></strong></label>
                <input type="text" name="roro_mag_title_<?php echo esc_attr($l); ?>" value="<?php echo esc_attr($t); ?>" />
                <label><strong><?php echo esc_html(strtoupper($l)); ?> <?php _e('Summary','roro-magazine'); ?></strong></label>
                <textarea name="roro_mag_summary_<?php echo esc_attr($l); ?>" rows="2"><?php echo esc_textarea($s); ?></textarea>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php _e('Use featured image as the issue cover.','roro-magazine'); ?></p>
        <?php
    }

    public function article_box($post) {
        wp_nonce_field('roro_mag_article_box', 'roro_mag_article_nonce');
        $svc = new RORO_Mag_Service();
        $issues = $svc->get_issues_for_dropdown();
        $issue_id = get_post_meta($post->ID, '_roro_mag_issue_id', true);
        $order = get_post_meta($post->ID, '_roro_mag_order', true);
        $langs = ['ja','en','zh','ko'];
        ?>
        <style>.roro-mag-grid{display:grid;grid-template-columns:140px 1fr;gap:8px;}</style>
        <div class="roro-mag-grid">
            <label><strong><?php _e('Issue','roro-magazine'); ?></strong></label>
            <select name="roro_mag_issue_id">
                <option value="0"><?php _e('— Select Issue —','roro-magazine'); ?></option>
                <?php foreach($issues as $i): ?>
                    <option value="<?php echo intval($i->ID); ?>" <?php selected($issue_id, $i->ID); ?>><?php echo esc_html(get_the_title($i->ID)); ?></option>
                <?php endforeach; ?>
            </select>

            <label><strong><?php _e('Order','roro-magazine'); ?></strong></label>
            <input type="number" name="roro_mag_order" value="<?php echo esc_attr($order !== '' ? $order : 0); ?>" min="0" step="1" />
            <?php foreach($langs as $l): 
                $t = get_post_meta($post->ID, "_roro_mag_title_{$l}", true);
                $e = get_post_meta($post->ID, "_roro_mag_excerpt_{$l}", true);
                $c = get_post_meta($post->ID, "_roro_mag_content_{$l}", true);
            ?>
                <label><strong><?php echo esc_html(strtoupper($l)); ?> <?php _e('Title','roro-magazine'); ?></strong></label>
                <input type="text" name="roro_mag_title_<?php echo esc_attr($l); ?>" value="<?php echo esc_attr($t); ?>" />

                <label><strong><?php echo esc_html(strtoupper($l)); ?> <?php _e('Excerpt','roro-magazine'); ?></strong></label>
                <textarea name="roro_mag_excerpt_<?php echo esc_attr($l); ?>" rows="2"><?php echo esc_textarea($e); ?></textarea>

                <label><strong><?php echo esc_html(strtoupper($l)); ?> <?php _e('Content (HTML allowed)','roro-magazine'); ?></strong></label>
                <textarea name="roro_mag_content_<?php echo esc_attr($l); ?>" rows="6"><?php echo esc_textarea($c); ?></textarea>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php _e('Use featured image for the lead visual.','roro-magazine'); ?></p>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['post_type']) && $_POST['post_type'] === 'roro_mag_issue') {
            if (!isset($_POST['roro_mag_issue_nonce']) || !wp_verify_nonce($_POST['roro_mag_issue_nonce'], 'roro_mag_issue_box')) return;
            update_post_meta($post_id, '_roro_mag_issue_key', sanitize_text_field($_POST['roro_mag_issue_key'] ?? ''));
            foreach(['ja','en','zh','ko'] as $l){
                update_post_meta($post_id, "_roro_mag_title_{$l}", sanitize_text_field($_POST["roro_mag_title_{$l}"] ?? ''));
                update_post_meta($post_id, "_roro_mag_summary_{$l}", wp_kses_post($_POST["roro_mag_summary_{$l}"] ?? ''));
            }
        }
        if (isset($_POST['post_type']) && $_POST['post_type'] === 'roro_mag_article') {
            if (!isset($_POST['roro_mag_article_nonce']) || !wp_verify_nonce($_POST['roro_mag_article_nonce'], 'roro_mag_article_box')) return;
            update_post_meta($post_id, '_roro_mag_issue_id', intval($_POST['roro_mag_issue_id'] ?? 0));
            update_post_meta($post_id, '_roro_mag_order', intval($_POST['roro_mag_order'] ?? 0));
            foreach(['ja','en','zh','ko'] as $l){
                update_post_meta($post_id, "_roro_mag_title_{$l}", sanitize_text_field($_POST["roro_mag_title_{$l}"] ?? ''));
                update_post_meta($post_id, "_roro_mag_excerpt_{$l}", wp_kses_post($_POST["roro_mag_excerpt_{$l}"] ?? ''));
                update_post_meta($post_id, "_roro_mag_content_{$l}", wp_kses_post($_POST["roro_mag_content_{$l}"] ?? ''));
            }
        }
    }

    // --- Admin columns for articles ---
    public function columns_articles($cols){
        $cols_new = [];
        foreach($cols as $k=>$v){
            if ($k==='date') continue;
            $cols_new[$k] = $v;
        }
        $cols_new['issue'] = __('Issue','roro-magazine');
        $cols_new['order'] = __('Order','roro-magazine');
        $cols_new['date']  = __('Date');
        return $cols_new;
    }
    public function columns_articles_content($col, $post_id){
        if ($col==='issue'){
            $iid = get_post_meta($post_id, '_roro_mag_issue_id', true);
            if ($iid){
                echo '<a href="'.esc_url(get_edit_post_link($iid)).'">'.esc_html(get_the_title($iid)).'</a>';
            }else{
                echo '—';
            }
        }elseif($col==='order'){
            echo esc_html(get_post_meta($post_id, '_roro_mag_order', true) ?: '0');
        }
    }
}
