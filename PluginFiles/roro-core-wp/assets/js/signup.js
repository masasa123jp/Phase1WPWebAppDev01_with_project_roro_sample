(function(){
  const {api, ui} = window.RORO;

  document.addEventListener('DOMContentLoaded', ()=>{
    const form = document.getElementById('roro-signup-form');
    if (!form) return;
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await api.post('auth/signup', { email: fd.get('email'), password: fd.get('password') });
        ui.toast('登録しました。ログイン済みです。');
        location.href = window.RORO.cfg.baseUrl;
      } catch (err) {
        ui.toast('登録に失敗しました');
        console.error(err);
      }
    });
  });
})();
