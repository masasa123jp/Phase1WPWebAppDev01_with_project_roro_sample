/*
  login.js – ログイン画面のイベントハンドラ

  ユーザーがログインフォームを送信した際に入力値を検証し、
  ローカルストレージへユーザー情報を保存してマップページへ遷移します。
  ソーシャルログインボタンは本番環境ではOAuthで処理しますが、
  このモックでは即座にログイン扱いとします。
*/

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = document.getElementById('login-email').value.trim();
      const password = document.getElementById('login-password').value.trim();
      if (!email || !password) {
        // 利用言語に基づいてバリデーションメッセージを取得
        const lang = typeof window.getUserLang === 'function' ? window.getUserLang() : 'ja';
        const t = (window.translations && window.translations[lang]) || {};
        alert(t.error_required_login_fields || 'Please enter your email and password');
        return;
      }
      // メールアドレス形式の簡易バリデーション
      const emailPattern = /^[\w.-]+@[\w.-]+\.[A-Za-z]{2,}$/;
      if (!emailPattern.test(email)) {
        const lang = typeof window.getUserLang === 'function' ? window.getUserLang() : 'ja';
        const t = (window.translations && window.translations[lang]) || {};
        alert(t.error_invalid_email_format || 'Please enter a valid email address');
        return;
      }
      // 登録済みユーザーの確認
      let registered;
      try {
        registered = JSON.parse(localStorage.getItem('registeredUser'));
      } catch (e) {
        registered = null;
      }
      // 登録済みユーザーが存在する場合はメールとパスワードを照合
      if (registered) {
        if (registered.email === email && registered.password === password) {
          const user = { ...registered };
          // セッションストレージに保存して、ブラウザ終了時に破棄されるようにする
          sessionStorage.setItem('user', JSON.stringify(user));
          // WordPress template uses .php instead of .html
          location.href = 'map.php';
        } else {
          // ユーザー認証失敗
          const lang = typeof window.getUserLang === 'function' ? window.getUserLang() : 'ja';
          const t = (window.translations && window.translations[lang]) || {};
          alert(t.error_user_mismatch || 'User not found or incorrect password.');
        }
      } else {
        // 登録ユーザーが存在しない場合はメールアドレスで簡易ログインを許可
        const user = { email, name: email.split('@')[0] };
        sessionStorage.setItem('user', JSON.stringify(user));
        location.href = 'map.php';
      }
    });
  }
  // Googleログイン
  const googleBtn = document.querySelector('.google-btn');
  if (googleBtn) {
    googleBtn.addEventListener('click', () => {
      const user = { email: 'google@example.com', name: 'Googleユーザー' };
      sessionStorage.setItem('user', JSON.stringify(user));
      location.href = 'map.php';
    });
  }
  // LINEログイン
  const lineBtn = document.querySelector('.line-btn');
  if (lineBtn) {
    lineBtn.addEventListener('click', () => {
      const user = { email: 'line@example.com', name: 'LINEユーザー' };
      sessionStorage.setItem('user', JSON.stringify(user));
      location.href = 'map.php';
    });
  }
});