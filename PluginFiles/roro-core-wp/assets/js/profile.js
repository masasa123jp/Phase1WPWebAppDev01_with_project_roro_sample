(function(){
  const {api, ui} = window.RORO;

  async function loadProfile() {
    try {
      const res = await api.get('profile');
      const pf = res.profile || {};
      const form = document.getElementById('roro-profile-form');
      if (!form) return;
      ['postal_code','country_code','prefecture','city','address_line1','address_line2','building'].forEach(k=>{
        if (form.elements[k]) form.elements[k].value = pf[k] || '';
      });
    } catch (e) {
      console.error(e);
      ui.toast('プロフィール取得に失敗しました');
    }
  }

  async function saveProfile(e) {
    e.preventDefault();
    const form = e.target;
    const body = {};
    ['postal_code','country_code','prefecture','city','address_line1','address_line2','building'].forEach(k=>{
      body[k] = form.elements[k]?.value || '';
    });
    try {
      await api.post('profile', body);
      ui.toast('保存しました');
    } catch (e) {
      console.error(e); ui.toast('保存に失敗しました');
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const form = document.getElementById('roro-profile-form');
    if (form) {
      loadProfile();
      form.addEventListener('submit', saveProfile);
    }
  });
})();
