/**
 * お気に入り：AJAXで登録/解除、トースト表示、★/☆トグル
 */
(function(){
  'use strict';

  function toast(msg){
    var el = document.getElementById('roro-fav-toast'); if (!el) return;
    el.textContent = msg; el.style.display='block'; el.style.opacity='1';
    setTimeout(function(){ el.style.opacity='0'; }, 1500);
    setTimeout(function(){ el.style.display='none'; }, 1800);
  }

  async function api(url, payload, method){
    // REST API呼び出しの汎用関数。メソッドはPOST/DELETEなどを受け取ります。
    const options = {
      method: method || 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': (window.RORO_FAV_CONFIG && RORO_FAV_CONFIG.rest && RORO_FAV_CONFIG.rest.nonce) || ''
      }
    };
    if (payload && options.method !== 'GET') {
      options.body = JSON.stringify(payload);
    }
    const res = await fetch(url, options);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    // レスポンスボディがJSONでない場合もあるのでtry/catch
    try {
      return await res.json();
    } catch (e) {
      return {};
    }
  }

  async function onToggle(ev){
    var btn = ev.currentTarget;
    var li = btn.closest('.roro-fav-item'); if (!li) return;
    var type = li.getAttribute('data-target') || 'event';
    var id = parseInt(li.getAttribute('data-id')||'0',10); if (!id) return;

    var pressed = btn.getAttribute('aria-pressed') === 'true';

    try {
      if (pressed) {
        // お気に入り解除: DELETE メソッドで送信
        await api(
          RORO_FAV_CONFIG.rest.remove,
          { target_type: type, target_id: id },
          'DELETE'
        );
        btn.setAttribute('aria-pressed', 'false');
        btn.textContent = '☆';
        toast((RORO_FAV_CONFIG.i18n && RORO_FAV_CONFIG.i18n.removed) || 'Removed');
      } else {
        // お気に入り登録: POST メソッドで送信
        await api(
          RORO_FAV_CONFIG.rest.add,
          { target_type: type, target_id: id },
          'POST'
        );
        btn.setAttribute('aria-pressed', 'true');
        btn.textContent = '★';
        toast((RORO_FAV_CONFIG.i18n && RORO_FAV_CONFIG.i18n.added) || 'Added');
      }
    } catch(e){
      console.error(e);
      toast(RORO_FAV_CONFIG.i18n.error || 'Error');
    }
  }

  function bind(){
    document.querySelectorAll('.roro-fav-toggle').forEach(function(b){
      b.addEventListener('click', onToggle);
      if (!b.hasAttribute('aria-pressed')) b.setAttribute('aria-pressed','true');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
