(function(){
  const {api, ui} = window.RORO;

  document.addEventListener('DOMContentLoaded', ()=>{
    const form = document.getElementById('roro-login-form');
    if (!form) return;
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await api.post('auth/login', { login: fd.get('login'), password: fd.get('password') });
        ui.toast('ログインしました');
        location.href = window.RORO.cfg.baseUrl;
      } catch (err) {
        ui.toast('ログインに失敗しました');
        console.error(err);
      }
    });
  });
})();
