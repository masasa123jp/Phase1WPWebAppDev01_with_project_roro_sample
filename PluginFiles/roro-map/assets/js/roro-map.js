// RORO Map front script
// - 依存：Google Maps JS（roro-map.phpがフッターでロード）
// - 役割：ショートコードが出力したコンテナをスキャンし、地図を初期化
// - data-markers 属性にJSON（[{lat,lng,title,desc,url}]）または data-src のJSON URLからマーカーを読み込む

(function() {
  "use strict";

  /**
   * JSONを安全にパース
   */
  function safeJSON(str) {
    if (!str) return null;
    try { return JSON.parse(str); } catch (e) { return null; }
  }

  /**
   * 単一コンテナの初期化
   */
  function initContainer(el) {
    var lat = parseFloat(el.getAttribute('data-lat') || '0');
    var lng = parseFloat(el.getAttribute('data-lng') || '0');
    var zoom = parseInt(el.getAttribute('data-zoom') || '12', 10);
    var markersStr = el.getAttribute('data-markers') || '';
    var src = el.getAttribute('data-src') || '';
    var msgs = (window.RORO_MAP_BOOT && RORO_MAP_BOOT.messages) ? RORO_MAP_BOOT.messages : {
      no_markers: 'No markers provided.',
      error_loading: 'Failed to load map data.',
      more: 'More'
    };

    var center = { lat: lat, lng: lng };
    var map = new google.maps.Map(el, {
      center: center,
      zoom: zoom
    });

    function addMarkers(list) {
      if (!list || !list.length) return;

      var info = new google.maps.InfoWindow();
      list.forEach(function(m) {
        if (typeof m.lat !== 'number' || typeof m.lng !== 'number') return;
        var mk = new google.maps.Marker({
          position: { lat: m.lat, lng: m.lng },
          map: map,
          title: m.title || ''
        });
        mk.addListener('click', function() {
          var html = '';
          if (m.title) html += '<div><strong>' + escapeHtml(m.title) + '</strong></div>';
          if (m.desc)  html += '<div>' + escapeHtml(m.desc) + '</div>';
          if (m.url)   html += '<div style="margin-top:6px;"><a href="' + encodeURI(m.url) + '">' + (msgs.more || 'More') + '</a></div>';
          info.setContent(html || (m.title || ''));
          info.open(map, mk);
        });
      });
    }

    var local = safeJSON(markersStr);
    if (local && Array.isArray(local)) {
      addMarkers(local);
    } else if (src) {
      // 外部/内部JSONをフェッチ（同一オリジン推奨）
      fetch(src, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (Array.isArray(json)) addMarkers(json);
        })
        .catch(function(){
          console.warn(msgs.error_loading || 'Failed to load map data.');
        });
    } else {
      // 指定が無い場合はメッセージのみ
      console.info(msgs.no_markers || 'No markers provided.');
    }
  }

  /**
   * HTMLエスケープ
   */
  function escapeHtml(s) {
    return (s+'')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  /**
   * 初期化
   */
  function boot() {
    // APIキーが無い場合はGoogle Mapsがロードされないため早期終了
    if (!window.google || !google.maps) return;
    var list = document.querySelectorAll('.roro-map-container');
    Array.prototype.forEach.call(list, initContainer);
  }

  // Google Mapsはasync deferで読まれるため、ポーリングで準備完了を待つ
  var tries = 0, timer = setInterval(function(){
    tries++;
    if (window.google && google.maps) {
      clearInterval(timer);
      boot();
    } else if (tries > 60) { // ~6秒
      clearInterval(timer);
      console.warn((window.RORO_MAP_BOOT && RORO_MAP_BOOT.messages && RORO_MAP_BOOT.messages.error_loading) || 'Failed to load Google Maps.');
    }
  }, 100);

})();
