(function(){
  const {api, ui, cfg} = window.RORO;

  async function loadSpots(params) {
    const list = document.getElementById('map-list');
    if (!list) return;
    list.innerHTML = 'Loading...';
    try {
      const res = await api.get('spots', params||{});
      list.innerHTML = '';
      (res.items||[]).forEach(item=>{
        const card = ui.el('div', {class:'card'}, [
          ui.el('h3', {html: item.name}),
          ui.el('p',  {html: [item.prefecture, item.address].filter(Boolean).join(' ')}),
          (item.distance_km!==undefined) ? ui.el('p', {html: `距離: ${item.distance_km}km`}) : '',
          item.url ? ui.el('a', {href:item.url, target:'_blank'}, 'Web') : ''
        ]);
        list.appendChild(card);
      });
    } catch (e) {
      console.error(e); ui.toast('スポット取得に失敗しました');
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const btn = document.getElementById('map-search-btn');
    const q   = document.getElementById('map-q');
    const pref= document.getElementById('map-pref');
    if (btn) btn.addEventListener('click', ()=>{
      loadSpots({q: q?.value||'', pref: pref?.value||''});
    });
    const list = document.getElementById('map-list');
    if (list) loadSpots({});
  });
})();
