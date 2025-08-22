(function(){
  const {api, ui} = window.RORO;

  async function loadMagazine(params) {
    const list = document.getElementById('magazine-list');
    if (!list) return;
    list.innerHTML = 'Loading...';
    try {
      const res = await api.get('magazine', params || {});
      list.innerHTML = '';
      (res.items||[]).forEach(item=>{
        const card = ui.el('div', {class:'card'}, [
          ui.el('h3', {html: item.title || '(no title)'}),
          ui.el('p',  {html: (item.body||'').substring(0, 180) + '...'}),
          item.url ? ui.el('a', {href:item.url, target:'_blank'}, '続きを読む') : ''
        ]);
        list.appendChild(card);
      });
    } catch (e) {
      console.error(e); ui.toast('読み込みに失敗しました');
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const btn = document.getElementById('mag-search-btn');
    const selC = document.getElementById('mag-category');
    const selP = document.getElementById('mag-pet-type');

    if (btn) btn.addEventListener('click', ()=>{
      loadMagazine({category: selC?.value || '', pet_type: selP?.value || 'DOG'});
    });
    // 初期表示
    const list = document.getElementById('magazine-list');
    if (list) loadMagazine({});
  });
})();
