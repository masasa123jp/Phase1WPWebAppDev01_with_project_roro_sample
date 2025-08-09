/*
  signup.js – 新規登録画面のイベントハンドラ

  登録フォームの送信時に入力値をチェックし、ユーザー情報とペット情報を
  ローカルストレージへ保存します。実際の環境ではここからサーバーへ
  リクエストを送信してユーザー登録を完了させますが、本モックでは
  完了後に直接マップページへ遷移します。
*/

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signup-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      // 必須項目を取得
      const name = document.getElementById('name').value.trim();
      const furigana = document.getElementById('furigana').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value.trim();
      const passwordConfirm = document.getElementById('passwordConfirm').value.trim();
      const petType = document.getElementById('petType').value;
      const petName = document.getElementById('petName') ? document.getElementById('petName').value.trim() : '';
      const petAge = document.getElementById('petAge').value;
      const address = document.getElementById('address').value.trim();
      const phone = document.getElementById('phone').value.trim();
      if (!name || !email || !password) {
        // 必須フィールドチェックの多言語化
        const lang = typeof window.getUserLang === 'function' ? window.getUserLang() : 'ja';
        const t = (window.translations && window.translations[lang]) || {};
        alert(t.error_required_signup_fields || 'Name, email, and password are required');
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
      // パスワードと確認が一致するかチェック
      if (password !== passwordConfirm) {
        const lang = typeof window.getUserLang === 'function' ? window.getUserLang() : 'ja';
        const t = (window.translations && window.translations[lang]) || {};
        alert(t.error_password_mismatch || 'Passwords do not match');
        return;
      }
      // 新しいデータ構造では pets 配列にペット情報を格納する
      const pets = [];
      if (petType) {
        pets.push({ type: petType, name: petName, age: petAge });
      }
      const user = {
        name,
        furigana,
        email,
        password,
        address,
        phone,
        pets
      };
      // 新規登録ユーザーを保存
      localStorage.setItem('registeredUser', JSON.stringify(user));
      // 現在のログインユーザーとしても保存
      localStorage.setItem('user', JSON.stringify(user));
      // WordPress template uses .php instead of .html
      location.href = 'map.php';
    });
  }
  // Google登録
  const googleBtn = document.querySelector('.google-btn');
  if (googleBtn) {
    googleBtn.addEventListener('click', () => {
      const user = { email: 'google@example.com', name: 'Googleユーザー' };
      localStorage.setItem('user', JSON.stringify(user));
      location.href = 'map.php';
    });
  }
  // LINE登録
  const lineBtn = document.querySelector('.line-btn');
  if (lineBtn) {
    lineBtn.addEventListener('click', () => {
      const user = { email: 'line@example.com', name: 'LINEユーザー' };
      localStorage.setItem('user', JSON.stringify(user));
      location.href = 'map.php';
    });
  }
});