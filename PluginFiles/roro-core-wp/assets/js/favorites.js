(function(){
  const {api, ui} = window.RORO;

  async function loadFav() {
    const list = document.getElementById('favorites-list');
    if (!list) return;
    list.innerHTML = 'Loading...';
    try {
      const res = await api.get('favorites');
      list.innerHTML = '';
      (res.items||[]).forEach(it=>{
        const card = ui.el('div', {class:'card'}, [
          ui.el('h3', {html: it.label || `${it.target_type}:${it.source_id||''}`}),
          ui.el('p',  {html: it.created_at || ''}),
          ui.el('button', {class:'btn-del', 'data-id': it.favorite_id}, '削除')
        ]);
        list.appendChild(card);
      });
      list.querySelectorAll('.btn-del').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const id = e.target.getAttribute('data-id');
          try {
            await fetch(RORO_BOOT.restUrl + 'favorites/' + id, {method:'DELETE', headers: {'X-WP-Nonce': RORO_BOOT.nonce}});
            loadFav();
          } catch (e) { ui.toast('削除に失敗しました'); }
        });
      });
    } catch (e) {
      console.error(e); ui.toast('取得に失敗しました');
    }
  }

  document.addEventListener('DOMContentLoaded', loadFav);
})();
