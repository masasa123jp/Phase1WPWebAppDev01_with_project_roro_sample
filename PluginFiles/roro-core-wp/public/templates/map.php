<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-map">
  <h2>Map</h2>
  <div class="map-search">
    <input type="text" id="map-q" placeholder="キーワード">
    <select id="map-pref"><option value="">都道府県</option></select>
    <button id="map-search-btn">検索</button>
  </div>
  <div id="map-list" class="grid"></div>
</div>
