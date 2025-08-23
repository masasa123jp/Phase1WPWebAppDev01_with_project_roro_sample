/*
 * roro-signup.js – 新規登録フォームのハンドラ
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signup-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    // 必須項目の取得
    const name     = document.getElementById('name').value.trim();
    const furigana = document.getElementById('furigana').value.trim();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const petType  = document.getElementById('petType').value;
    const petName  = document.getElementById('petName').value.trim();
    const petAge   = document.getElementById('petAge').value;
    const address  = document.getElementById('address').value.trim();
    const phone    = document.getElementById('phone').value.trim();

    if (!name || !email || !password) {
      alert('名前、メールアドレス、パスワードは必須です');
      return;
    }

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

    // ローカルストレージに保存（モック）
    localStorage.setItem('registeredUser', JSON.stringify(user));
    sessionStorage.setItem('user', JSON.stringify(user));
    location.href = '/map';
  });

  // ソーシャル登録（モック）
  document.querySelector('.google-btn')?.addEventListener('click', () => {
    const user = { email:'google@example.com', name:'Googleユーザー', pets: [] };
    localStorage.setItem('registeredUser', JSON.stringify(user));
    sessionStorage.setItem('user', JSON.stringify(user));
    location.href = '/map';
  });
  document.querySelector('.line-btn')?.addEventListener('click', () => {
    const user = { email:'line@example.com', name:'LINEユーザー', pets: [] };
    localStorage.setItem('registeredUser', JSON.stringify(user));
    sessionStorage.setItem('user', JSON.stringify(user));
    location.href = '/map';
  });
});
