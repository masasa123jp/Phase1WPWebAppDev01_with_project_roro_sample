(function(){
  function $(s){ return document.querySelector(s); }
  function msg(t){ const m=$('#roro-home-msg'); if(m){ m.textContent=t; } }
  document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('roro-home-ui'); if(!wrap) return;
    const rest = wrap.dataset.rest; const nonce = wrap.dataset.nonce;
    const latEl = document.getElementById('roro-lat'); const lngEl = document.getElementById('roro-lng');
    document.getElementById('roro-use-geo')?.addEventListener('click', () => {
      if(!navigator.geolocation){ msg('この端末は位置情報に対応していません。'); return; }
      navigator.geolocation.getCurrentPosition((pos)=>{
        latEl.value = pos.coords.latitude.toFixed(6);
        lngEl.value = pos.coords.longitude.toFixed(6);
        msg('現在地を取得しました。');
      }, (err)=>{ msg('現在地の取得に失敗: '+err.message); });
    });
    document.getElementById('roro-save-home')?.addEventListener('click', async ()=>{
      const lat = parseFloat(latEl.value), lng = parseFloat(lngEl.value);
      if(isNaN(lat)||isNaN(lng)){ msg('緯度経度を入力してください。'); return; }
      const res = await fetch(rest, { method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, body: JSON.stringify({ lat, lng }) });
      if(res.ok){ msg('保存しました。'); } else { msg('保存に失敗しました。'); }
    });
  });
})();
