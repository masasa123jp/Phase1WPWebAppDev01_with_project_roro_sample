<?php
// Bottom navigation appears except on front page and signup page
if (!is_front_page() && !is_page_template('page-signup.php')) : ?>
<nav class="bottom-nav">
    <a href="<?php echo esc_url(home_url('/map/')); ?>" class="nav-item<?php echo is_page_template('page-map.php') ? ' active' : ''; ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/images/icon_map.png" alt="Map" /><span data-i18n-key="nav_map">マップ</span></a>
    <a href="<?php echo esc_url(home_url('/dify/')); ?>" class="nav-item<?php echo is_page_template('page-dify.php') ? ' active' : ''; ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/images/icon_ai.png" alt="AI" /><span data-i18n-key="nav_ai">AI</span></a>
    <a href="<?php echo esc_url(home_url('/favorites/')); ?>" class="nav-item<?php echo is_page_template('page-favorites.php') ? ' active' : ''; ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/images/icon_favorite.png" alt="お気に入り" /><span data-i18n-key="nav_favorites">お気に入り</span></a>
    <a href="<?php echo esc_url(home_url('/magazine/')); ?>" class="nav-item<?php echo is_page_template('page-magazine.php') ? ' active' : ''; ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/images/icon_magazine.png" alt="雑誌" /><span data-i18n-key="nav_magazine">雑誌</span></a>
    <a href="<?php echo esc_url(home_url('/profile/')); ?>" class="nav-item<?php echo is_page_template('page-profile.php') ? ' active' : ''; ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/images/icon_profile.png" alt="マイページ" /><span data-i18n-key="nav_profile">マイページ</span></a>
</nav>
<?php endif; ?>
<!-- フッターリンク: 利用規約・会社情報 -->
<div class="footer-links" style="text-align:center; margin-top:1rem; font-size:0.8rem;">
  <a href="<?php echo esc_url( home_url('/terms/') ); ?>" target="_blank" rel="noopener">利用規約</a>
  |
  <a href="<?php echo esc_url( home_url('/company/') ); ?>" target="_blank" rel="noopener">会社情報</a>
</div>
<?php wp_footer(); ?>
</body>
</html>
