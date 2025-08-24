<?php
/**
 * RORO App main container (shortcode: [roro_app])
 */
if (!defined('ABSPATH')) exit;
?>
<div class="roro-container">
  <header class="roro-header">
    <img src="<?php echo esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png'); ?>" alt="RORO" class="roro-logo">
    <nav class="roro-nav" id="roro-nav">
      <!-- JS が BOOT データと i18n でテキスト/リンクを生成 -->
    </nav>
  </header>

  <section id="magazine" class="roro-section">
    <h2 data-i18n="magazine">Magazine</h2>
    <div id="magazine-list" class="grid" aria-live="polite"></div>
  </section>

  <section id="map" class="roro-section">
    <h2 data-i18n="map">Map</h2>
    <div class="map-search">
      <input type="text" id="map-q" placeholder="" data-ph="keyword" aria-label="keyword">
      <select id="map-pref" aria-label="prefecture">
        <option value="" data-i18n="prefecture">Prefecture</option>
      </select>
      <button id="map-search-btn" data-i18n="search">Search</button>
    </div>
    <div id="map-canvas" class="map-canvas" role="region" aria-label="map"></div>
    <div id="map-list" class="grid"></div>
  </section>

  <section id="favorites" class="roro-section">
    <h2 data-i18n="favorites">Favorites</h2>
    <div id="favorites-list" class="grid"></div>
  </section>

  <section id="profile" class="roro-section">
    <h2 data-i18n="profile">Profile</h2>
    <form id="profile-form">
      <label>
        <span>Display Name</span>
        <input name="display_name" type="text">
      </label>
      <label>
        <span>First Name</span>
        <input name="first_name" type="text">
      </label>
      <label>
        <span>Last Name</span>
        <input name="last_name" type="text">
      </label>
      <label>
        <span>Locale</span>
        <input name="locale" type="text">
      </label>
      <button type="submit" data-i18n="save">Save</button>
      <output id="profile-msg" role="status" aria-live="polite"></output>
    </form>
  </section>

  <section id="ai" class="roro-section">
    <h2 data-i18n="ai">AI Assistant</h2>
    <div class="ai-box">
      <div id="ai-log" class="ai-log" aria-live="polite"></div>
      <div class="ai-input">
        <input id="ai-text" type="text" placeholder="Ask..." />
        <button id="ai-send">Send</button>
      </div>
    </div>
  </section>
</div>
