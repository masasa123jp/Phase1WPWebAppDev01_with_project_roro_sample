<?php
if (!defined('ABSPATH')) { exit; }
$M = $data['M']; $A = $data['article'];
?>
<article class="roro-mag-single">
  <?php if($A['image']): ?><img class="roro-mag-article-img" src="<?php echo esc_url($A['image']); ?>" alt="" /><?php endif; ?>
  <h2><?php echo esc_html($A['title']); ?></h2>
  <div class="roro-mag-content"><?php echo wp_kses_post($A['content']); ?></div>
</article>
