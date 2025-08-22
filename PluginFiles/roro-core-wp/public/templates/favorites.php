<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-favorites">
  <h2>Favorites</h2>
  <?php if (!is_user_logged_in()): ?>
    <p>ログインが必要です。</p>
  <?php else: ?>
    <div id="favorites-list" class="grid"></div>
  <?php endif; ?>
</div>
