(function(){
  'use strict';

  // ---------------------------
  // ユーティリティ
  // ---------------------------
  function $(sel, root){ return (root||document).querySelector(sel); }
  function $all(sel, root){ return (root||document).querySelectorAll(sel); }

  async function api(path, opts){
    const url = (RORO.restUrl.replace(/\/+$/, '')) + '/' + path.replace(/^\/+/, '');
    const headers = {'Content-Type': 'application/json'};
    // 認証付きエンドポイント用（ログイン済時）
    if (RORO.nonce) headers['X-WP-Nonce'] = RORO.nonce;

    const res = await fetch(url, {
      method: (opts && opts.method) || 'GET',
      headers,
      credentials: 'same-origin',
      body: (opts && opts.body) ? JSON.stringify(opts.body) : undefined
    });
    if (!res.ok) {
      let msg = 'Request failed (' + res.status + ')';
      try {
        const j = await res.json();
        if (j && j.message) msg = j.message;
      } catch(e){}
      throw new Error(msg);
    }
    return res.json();
  }

  function cardHTML(item){
    const safe = (v) => (v==null?'':String(v));
    return [
      '<div class="card" data-id="', safe(item.id), '">',
        '<div class="card-body">',
          '<h3>', safe(item.title), '</h3>',
          item.start_at ? ('<div class="meta">'+safe(item.start_at)+'</div>') : '',
          item.address  ? ('<div class="meta">'+safe(item.address)+'</div>') : '',
          '<div class="actions">',
            '<button class="roro-btn add-fav" data-type="event" data-id="', safe(item.id), '">★お気に入り</button>',
            item.permalink ? ('<a class="roro-btn outline" target="_blank" rel="noopener" href="'+safe(item.permalink)+'">詳細</a>') : '',
          '</div>',
        '</div>',
      '</div>'
    ].join('');
  }

  function renderList(items, root){
    root.innerHTML = items.map(cardHTML).join('');
  }

  function showToast(msg){
    alert(msg); // 最小実装。必要に応じてトーストUIへ差し替え可
  }

  // ---------------------------
  // イベント一覧
  // ---------------------------
  async function loadEvents(keyword){
    const params = new URLSearchParams();
    if (keyword) params.set('q', String(keyword));
    try {
      const data = await api('events' + (params.toString()?('?'+params.toString()):''), {method:'GET'});
      const items = (data && data.items) || [];
      const root = $('#map-list');
      renderList(items, root);
      if (Array.isArray(items) && items.length && RORO.mapApiKey) {
        ensureGoogleMap().then(() => renderMap(items));
      } else {
        // キーなし or 0件の場合はカード表示のみ
        $('#map-canvas').style.display = 'none';
      }
    } catch(e) {
      showToast('イベントの取得に失敗しました: ' + e.message);
    }
  }

  // ---------------------------
  // Google Maps（キーがあれば動的ロード）
  // ---------------------------
  let map;
  function renderMap(items){
    const canvas = $('#map-canvas');
    canvas.style.display = 'block';
    if (!map) {
      map = new google.maps.Map(canvas, {
        center: {lat: 35.681236, lng: 139.767125},
        zoom: 11,
      });
    }
    const bounds = new google.maps.LatLngBounds();
    items.forEach(it => {
      const lat = parseFloat(it.lat); const lng = parseFloat(it.lng);
      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        const mk = new google.maps.Marker({
          position: {lat, lng},
          map,
          title: it.title || '',
        });
        bounds.extend(mk.getPosition());
      }
    });
    if (!bounds.isEmpty()) map.fitBounds(bounds);
  }

  let gmapLoading = null;
  function ensureGoogleMap(){
    if (!RORO.mapApiKey) return Promise.resolve();
    if (window.google && window.google.maps) return Promise.resolve();
    if (gmapLoading) return gmapLoading;

    gmapLoading = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(RORO.mapApiKey);
      s.async = true; s.defer = true;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Failed to load Google Maps JS'));
      document.head.appendChild(s);
    });
    return gmapLoading;
  }

  // ---------------------------
  // お気に入り
  // ---------------------------
  async function addFavorite(objectId, objectType){
    if (!RORO.loggedIn) { showToast('ログインしてください'); return; }
    try {
      const data = await api('favorites', {method:'POST', body:{object_id: Number(objectId), object_type: String(objectType)}});
      showToast('お気に入りに追加しました');
      refreshFavorites(); // 再読込
    } catch(e) {
      showToast('お気に入り追加に失敗: ' + e.message);
    }
  }

  async function removeFavorite(objectId, objectType){
    try {
      const data = await api('favorites?object_id='+Number(objectId)+'&object_type='+encodeURIComponent(objectType), {method:'DELETE'});
      showToast('お気に入りを削除しました');
      refreshFavorites();
    } catch(e) {
      showToast('お気に入り削除に失敗: ' + e.message);
    }
  }

  async function refreshFavorites(){
    if (!RORO.loggedIn) { $('#favorites-list').innerHTML = '<div class="muted">ログインが必要です</div>'; return; }
    try {
      const list = await api('favorites', {method:'GET'});
      const root = $('#favorites-list');
      if (!Array.isArray(list) || list.length === 0) {
        root.innerHTML = '<div class="muted">お気に入りがありません</div>';
        return;
      }
      // 最小表示（event のみ想定）。必要なら個別詳細を再取得して描画拡張
      root.innerHTML = list.map(row => {
        return '<div class="card"><div class="card-body"><div>'+String(row.object_type)+' #'+String(row.object_id)+'</div><div class="actions"><button class="roro-btn danger rm-fav" data-type="'+String(row.object_type)+'" data-id="'+Number(row.object_id)+'">削除</button></div></div></div>';
      }).join('');
    } catch(e) {
      $('#favorites-list').innerHTML = '<div class="error">読み込みに失敗：'+e.message+'</div>';
    }
  }

  // ---------------------------
  // ログアウト
  // ---------------------------
  async function doLogout(){
    try {
      await api('logout', {method:'POST'});
      showToast('ログアウトしました');
      location.reload();
    } catch(e) {
      showToast('ログアウトに失敗: ' + e.message);
    }
  }

  // ---------------------------
  // 画面初期化
  // ---------------------------
  function bind(){
    // 検索
    $('#map-search-btn')?.addEventListener('click', () => {
      const q = $('#map-q')?.value || '';
      loadEvents(q);
    });

    // お気に入り追加（イベント委譲）
    document.body.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.add-fav');
      if (btn) {
        addFavorite(btn.getAttribute('data-id'), btn.getAttribute('data-type'));
      }
      const rm = ev.target.closest('.rm-fav');
      if (rm) {
        removeFavorite(rm.getAttribute('data-id'), rm.getAttribute('data-type'));
      }
    });

    // ログアウト
    $('#roro-logout')?.addEventListener('click', doLogout);
  }

  document.addEventListener('DOMContentLoaded', () => {
    bind();
    loadEvents('');
    refreshFavorites();
    // 雑誌は別途：UI モック（ここでは空リストのまま）
  });
})();
