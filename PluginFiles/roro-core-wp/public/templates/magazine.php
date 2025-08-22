<?php if (!defined('ABSPATH')) exit; ?>
<div class="roro-magazine">
  <h2>Magazine</h2>
  <div class="magazine-filter">
    <select id="mag-category"><option value="">カテゴリ</option></select>
    <select id="mag-pet-type">
      <option value="DOG">DOG</option>
      <option value="CAT">CAT</option>
      <option value="OTHER">OTHER</option>
    </select>
    <button id="mag-search-btn">読込</button>
  </div>
  <div id="magazine-list" class="grid"></div>
</div>
