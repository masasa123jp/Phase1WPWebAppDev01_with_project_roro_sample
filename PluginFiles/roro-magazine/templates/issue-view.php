<?php
if (!defined('ABSPATH')) { exit; }
$svc = new RORO_Mag_Service();
$lang = $data['lang']; $M = $data['M']; $issue_id = intval($data['issue_id']);
$issue = get_post($issue_id);
if (!$issue) { echo '<div class="roro-mag-empty">'.esc_html($M['no_issues']).'</div>'; return; }
$IP = $svc->issue_payload($issue, $lang);
$articles = $svc->list_articles_by_issue($issue_id);
?>
<div class="roro-mag-wrap">
  <a class="roro-mag-back" href="<?php echo esc_url(remove_query_arg('mag_issue')); ?>">&laquo; <?php echo esc_html($M['back_to_list']); ?></a>
  <div class="roro-mag-hero">
    <?php if($IP['cover']): ?><img src="<?php echo esc_url($IP['cover']); ?>" alt="" /><?php endif; ?>
    <div class="roro-mag-hero-text">
      <h2><?php echo esc_html($IP['title']); ?></h2>
      <?php if($IP['summary']): ?><p><?php echo esc_html($IP['summary']); ?></p><?php endif; ?>
      <div class="roro-mag-issuekey"><?php echo esc_html($M['issue_key']); ?>: <?php echo esc_html($IP['issue_key'] ?: get_the_date('Y-m', $issue)); ?></div>
    </div>
  </div>

  <?php if (!$articles): ?>
    <div class="roro-mag-empty"><?php echo esc_html($M['no_articles']); ?></div>
  <?php else: ?>
    <div class="roro-mag-articles">
      <?php foreach($articles as $a):
        $AP = $svc->article_payload($a, $lang);
      ?>
        <article class="roro-mag-article">
          <?php if($AP['image']): ?><img class="roro-mag-article-img" src="<?php echo esc_url($AP['image']); ?>" alt="" /><?php endif; ?>
          <h3><?php echo esc_html($AP['title']); ?></h3>
          <?php if($AP['excerpt']): ?><p class="roro-mag-excerpt"><?php echo wp_kses_post($AP['excerpt']); ?></p><?php endif; ?>
          <div class="roro-mag-content" data-collapsed="true">
            <?php echo wp_kses_post($AP['content']); ?>
          </div>
          <button class="button roro-mag-toggle" type="button"><?php echo esc_html($M['read_more']); ?></button>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
