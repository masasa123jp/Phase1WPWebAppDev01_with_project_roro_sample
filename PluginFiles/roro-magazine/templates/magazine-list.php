<?php
if (!defined('ABSPATH')) { exit; }
$svc = new RORO_Mag_Service();
if ($data['issueId']) {
    // 号ビューに切り替え
    $data2 = [
        'lang' => $data['lang'],
        'M'    => $data['M'],
        'issue_id' => $data['issueId']
    ];
    include RORO_MAG_PLUGIN_DIR . 'templates/issue-view.php';
    return;
}
$lang = $data['lang']; $M = $data['M'];
$posts = $svc->list_issues(
    isset($data['limit']) ? intval($data['limit']) : 12,
    isset($data['offset']) ? intval($data['offset']) : 0
);
?>
<div class="roro-mag-wrap">
  <h2 class="roro-mag-title"><?php echo esc_html($M['magazine']); ?></h2>
  <?php if (!$posts): ?>
    <div class="roro-mag-empty"><?php echo esc_html($M['no_issues']); ?></div>
  <?php else: ?>
  <div class="roro-mag-grid">
    <?php foreach($posts as $p):
        $it = $svc->issue_payload($p, $lang);
        $link = add_query_arg('mag_issue', $it['id']);
    ?>
      <article class="roro-mag-card">
        <?php if($it['cover']): ?>
          <div class="roro-mag-cover" style="background-image:url('<?php echo esc_url($it['cover']); ?>')"></div>
        <?php endif; ?>
        <div class="roro-mag-body">
          <div class="roro-mag-issuekey"><?php echo esc_html($M['issue_key']); ?>: <?php echo esc_html($it['issue_key'] ?: get_the_date('Y-m', $p)); ?></div>
          <h3 class="roro-mag-card-title"><?php echo esc_html($it['title']); ?></h3>
          <?php if($it['summary']): ?>
            <p class="roro-mag-summary"><?php echo esc_html($it['summary']); ?></p>
          <?php endif; ?>
          <a class="button button-primary" href="<?php echo esc_url($link); ?>"><?php echo esc_html($M['view_issue']); ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
