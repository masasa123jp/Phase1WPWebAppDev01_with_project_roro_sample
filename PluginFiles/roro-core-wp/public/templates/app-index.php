<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-container">
  <header class="roro-header">
    <img src="<?php echo esc_url(RORO_CORE_URL.'assets/images/logo_roro.png'); ?>" alt="RORO" class="roro-logo">
    <nav class="roro-nav">
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home'); ?></a>
      <?php if (is_user_logged_in()): ?>
        <a href="<?php echo esc_url(get_permalink(get_option('page_on_front'))); ?>#profile"><?php esc_html_e('Profile'); ?></a>
        <a href="<?php echo esc_url(get_permalink(get_option('page_on_front'))); ?>#favorites"><?php esc_html_e('Favorites'); ?></a>
      <?php else: ?>
        <a href="<?php echo esc_url( site_url('/login/') ); ?>">Login</a>
        <a href="<?php echo esc_url( site_url('/signup/') ); ?>">Sign up</a>
      <?php endif; ?>
    </nav>
  </header>

  <section id="magazine" class="roro-section">
    <h2>Magazine</h2>
    <div id="magazine-list" class="grid"></div>
  </section>

  <section id="map" class="roro-section">
    <h2>Map</h2>
    <div class="map-search">
      <input type="text" id="map-q" placeholder="キーワード">
      <select id="map-pref"><option value="">都道府県</option></select>
      <button id="map-search-btn">検索</button>
    </div>
    <div id="map-list" class="grid"></div>
  </section>

  <section id="favorites" class="roro-section">
    <h2>Favorites</h2>
    <div id="favorites-list" class="grid"></div>
  </section>

  <section id="ai" class="roro-section">
    <h2>AI Assistant</h2>
    <div id="roro-ai-switch" data-mode="dify"></div>
  </section>
</div>
