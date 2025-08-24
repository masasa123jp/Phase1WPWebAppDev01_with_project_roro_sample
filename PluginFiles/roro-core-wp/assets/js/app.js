(function () {
  'use strict';

  var BOOT = window.RORO_CORE_BOOT || {};
  var REST = BOOT.rest || {};
  var AUTH = BOOT.auth || {};
  var SETTINGS = BOOT.settings || {};

  function headers(json) {
    var h = { 'X-WP-Nonce': REST.nonce };
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }

  // --------------------- Nav / Header ---------------------
  function buildNav() {
    var nav = document.getElementById('roro-nav');
    if (!nav) return;
    var items = [
      { href: '#magazine', key: 'magazine', icon: BOOT.assets.icon_mag },
      { href: '#map',      key: 'map',      icon: BOOT.assets.icon_map },
      { href: '#favorites',key: 'favorites',icon: BOOT.assets.icon_fav },
      { href: '#profile',  key: 'profile',  icon: BOOT.assets.icon_prof },
      { href: '#ai',       key: 'ai',       icon: BOOT.assets.icon_ai }
    ];
    nav.innerHTML = items.map(function (it) {
      return '<a href="'+it.href+'"><img alt="" src="'+it.icon+'" />'+window.RORO_I18N.t(it.key)+'</a>';
    }).join('') + (AUTH.isLoggedIn
      ? '<button id="roro-logout">'+window.RORO_I18N.t('logout')+'</button>'
      : '<a href="'+BOOT.site.homeUrl+'wp-login.php">'+window.RORO_I18N.t('login')+'</a>');
    var btn = document.getElementById('roro-logout');
    if (btn) btn.addEventListener('click', doLogout);
  }

  function doLogout() {
    fetch(REST.root+'auth/logout', { method:'POST', headers: headers(false) })
      .then(function(r){return r.json();})
      .then(function(){ location.reload(); })
      .catch(console.error);
  }

  // --------------------- Magazine (mock list) -------------
  function renderMagazine() {
    var list = document.getElementById('magazine-list');
    if (!list) return;
    var items = [
      { title: '2025-06', cover: BOOT.assets.logo, url: '#' },
      { title: '2025-07', cover: BOOT.assets.logo, url: '#' }
    ];
    list.innerHTML = items.map(function (x) {
      return '<article class="card"><img alt="" src="'+x.cover+'"><h3>'+x.title+'</h3></article>';
    }).join('');
  }

  // --------------------- Map / Events ---------------------
  var MAP, G;
  function injectGoogleMaps(key, cb) {
    if (!key) { cb && cb(new Error('No API key')); return; }
    var id = 'gmaps-sdk';
    if (document.getElementById(id)) { cb && cb(); return; }
    var s = document.createElement('script');
    s.id = id;
    s.async = true;
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key);
    s.onload = function(){ cb && cb(); };
    s.onerror = function(){ cb && cb(new Error('Failed to load Google Maps')); };
    document.head.appendChild(s);
  }

  function initMap() {
    var canvas = document.getElementById('map-canvas');
    if (!canvas) return;
    if (!SETTINGS.googleMapsApiKey) {
      canvas.innerHTML = '<p>Google Maps API key not set.</p>';
      return;
    }
    injectGoogleMaps(SETTINGS.googleMapsApiKey, function(err){
      if (err) { canvas.innerHTML = '<p>'+window.RORO_I18N.t('error')+'</p>'; return; }
      G = window.google;
      MAP = new G.maps.Map(canvas, { center: {lat:35.6812, lng:139.7671}, zoom: 10 });
      bindSearch();
      fetchEvents();
    });
  }

  function bindSearch() {
    var btn = document.getElementById('map-search-btn');
    btn && btn.addEventListener('click', fetchEvents);
  }

  function fetchEvents() {
    var q = document.getElementById('map-q').value.trim();
    var pref = document.getElementById('map-pref').value;
    var url = REST.root + 'events?q=' + encodeURIComponent(q) + (pref ? '&pref='+encodeURIComponent(pref) : '');
    fetch(url, { headers: headers(false) })
      .then(function(r){return r.json();})
      .then(function(data){ renderEvents(data.items || []); })
      .catch(console.error);
  }

  function renderEvents(items) {
    var list = document.getElementById('map-list');
    list.innerHTML = (items || []).map(function (e) {
      var favBtn = AUTH.isLoggedIn
        ? '<button data-fav="'+e.id+'">'+window.RORO_I18N.t('addFavorite')+'</button>'
        : '';
      return '<article class="card"><h3>'+e.title+'</h3><p>'+ (e.address || '') +'</p>'+favBtn+'</article>';
    }).join('');

    // マーカー
    if (G && MAP) {
      items.forEach(function (e) {
        if (!e.lat || !e.lng) return;
        var m = new G.maps.Marker({ position: {lat: e.lat, lng: e.lng}, map: MAP, title: e.title });
        var iw = new G.maps.InfoWindow({ content: '<strong>'+e.title+'</strong><br>'+ (e.address||'') });
        m.addListener('click', function(){ iw.open({anchor:m, map:MAP}); });
      });
    }

    // お気に入り
    list.querySelectorAll('[data-fav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-fav'), 10);
        addFavorite(id, btn.closest('.card').querySelector('h3').textContent);
      });
    });
  }

  // --------------------- Favorites ------------------------
  function loadFavorites() {
    if (!AUTH.isLoggedIn) return;
    fetch(REST.root + 'favorites', { headers: headers(false) })
      .then(function(r){return r.json();})
      .then(renderFavorites)
      .catch(console.error);
  }

  function renderFavorites(items) {
    var list = document.getElementById('favorites-list');
    if (!list) return;
    list.innerHTML = (items || []).map(function (e) {
      return '<article class="card"><h3>'+e.title+'</h3><button data-unfav="'+e.id+'">'+window.RORO_I18N.t('removeFavorite')+'</button></article>';
    }).join('');
    list.querySelectorAll('[data-unfav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-unfav'), 10);
        removeFavorite(id);
      });
    });
  }

  function addFavorite(id, title) {
    fetch(REST.root + 'favorites', {
      method: 'POST',
      headers: headers(true),
      body: JSON.stringify({ id: id, title: title })
    }).then(function(){ loadFavorites(); })
      .catch(console.error);
  }

  function removeFavorite(id) {
    fetch(REST.root + 'favorites/' + id, {
      method: 'DELETE',
      headers: headers(false)
    }).then(function(){ loadFavorites(); })
      .catch(console.error);
  }

  // --------------------- Profile --------------------------
  function initProfileForm() {
    var form = document.getElementById('profile-form');
    if (!form || !AUTH.isLoggedIn) return;
    // 初期値取得
    fetch(REST.root + 'profile', { headers: headers(false) })
      .then(function(r){ return r.json(); })
      .then(function(p){
        form.elements.display_name.value = p.display_name || '';
        form.elements.first_name.value   = p.first_name || '';
        form.elements.last_name.value    = p.last_name || '';
        form.elements.locale.value       = p.locale || '';
      });
    // 保存
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var payload = {
        display_name: form.elements.display_name.value.trim(),
        first_name:   form.elements.first_name.value.trim(),
        last_name:    form.elements.last_name.value.trim(),
        locale:       form.elements.locale.value.trim()
      };
      fetch(REST.root + 'profile', {
        method: 'POST',
        headers: headers(true),
        body: JSON.stringify(payload)
      }).then(function(r){return r.json();})
        .then(function(){ document.getElementById('profile-msg').textContent = window.RORO_I18N.t('saved'); })
        .catch(function(){ document.getElementById('profile-msg').textContent = window.RORO_I18N.t('error'); });
    });
  }

  // --------------------- AI (dummy proxy) -----------------
  function initAI() {
    var btn = document.getElementById('ai-send');
    var input = document.getElementById('ai-text');
    var log = document.getElementById('ai-log');
    if (!btn || !input || !log) return;
    btn.addEventListener('click', function () {
      var text = input.value.trim();
      if (!text) return;
      log.innerHTML += '<div class="msg me">'+text+'</div>';
      input.value = '';
      fetch(REST.root + 'ai/proxy', {
        method: 'POST',
        headers: headers(true),
        body: JSON.stringify({ message: text })
      }).then(function(r){return r.json();})
        .then(function(res){ log.innerHTML += '<div class="msg bot">'+(res.reply || '')+'</div>'; })
        .catch(function(){ log.innerHTML += '<div class="msg bot">'+window.RORO_I18N.t('error')+'</div>'; });
    });
  }

  // --------------------- boot -----------------------------
  document.addEventListener('DOMContentLoaded', function () {
    // i18n 適用
    window.RORO_I18N.applyDom(document);
    // ナビ
    buildNav();
    // マガジン
    renderMagazine();
    // 地図・イベント
    initMap();
    // お気に入り
    loadFavorites();
    // プロフィール
    initProfileForm();
    // AI
    initAI();
  });
})();
