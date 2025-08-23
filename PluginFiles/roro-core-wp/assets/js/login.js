/*
 * roro-login.js – ログイン画面のイベントハンドラ
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = document.getElementById('login-email').value.trim();
      const password = document.getElementById('login-password').value.trim();
      if (!email || !password) {
        alert('メールアドレスとパスワードを入力してください');
        return;
      }
      // 本モックではローカルストレージで仮ログイン扱いとする
      let reg = null;
      try { reg = JSON.parse(localStorage.getItem('registeredUser')); } catch(e){ reg = null; }
      if (reg && reg.email === email && reg.password === password) {
        sessionStorage.setItem('user', JSON.stringify(reg));
        location.href = '/map';
      } else {
        const user = { email, name: email.split('@')[0] };
        sessionStorage.setItem('user', JSON.stringify(user));
        location.href = '/map';
      }
    });
  }

  // Googleログイン（本番はOAuthに置換）
  document.querySelector('.google-btn')?.addEventListener('click', () => {
    const user = { email:'google@example.com', name:'Googleユーザー' };
    sessionStorage.setItem('user', JSON.stringify(user));
    location.href = '/map';
  });
  // LINEログイン（本番はLIFF連携に置換）
  document.querySelector('.line-btn')?.addEventListener('click', () => {
    const user = { email:'line@example.com', name:'LINEユーザー' };
    sessionStorage.setItem('user', JSON.stringify(user));
    location.href = '/map';
  });
});
