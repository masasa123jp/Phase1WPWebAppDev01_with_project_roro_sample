<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="roro-events-wrap">
  <form class="roro-events-filters" id="roro-events-filters" onsubmit="return false;">
    <input type="text" id="roro-q" placeholder="<?php echo esc_attr(RORO_EVENTS_CFG['i18n']['search_placeholder']); ?>" />
    <select id="roro-category" multiple></select>
    <input type="date" id="roro-date-from" />
    <input type="date" id="roro-date-to" />
    <div class="roro-nearby">
      <input type="number" id="roro-radius" value="<?php echo intval(RORO_EVENTS_CFG['defaults']['radiusKm']); ?>" min="1" max="200" step="1" /> km
      <button type="button" id="roro-use-geo"><?php echo esc_html(RORO_EVENTS_CFG['i18n']['use_my_location']); ?></button>
      <span id="roro-geo-status"></span>
    </div>
    <button type="button" id="roro-search"><?php echo esc_html(RORO_EVENTS_CFG['i18n']['search']); ?></button>
    <button type="button" id="roro-reset"><?php echo esc_html(RORO_EVENTS_CFG['i18n']['reset']); ?></button>
  </form>

  <div class="roro-events-layout">
    <div id="roro-map"></div>
    <div class="roro-list" id="roro-list"></div>
  </div>
</div>
