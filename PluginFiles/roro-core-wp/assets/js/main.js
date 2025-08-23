/*
 * roro-main.js – 共通処理
 * - ページ遷移時にナビゲーションをハイライト
 * - セッション・ローカルストレージの初期化
 */
document.addEventListener('DOMContentLoaded', () => {
  highlightNavigation();

  // デフォルト登録ユーザー（デモ用）
  try {
    const registered = localStorage.getItem('registeredUser');
    if (!registered) {
      const defaultUser = {
        name:'testユーザー',
        email:'test@test.com',
        password:'testtest!!test12345@',
        petType:'dog',
        petAge:'adult',
        address:'東京都豊島区池袋4丁目',
        phone:''
      };
      localStorage.setItem('registeredUser', JSON.stringify(defaultUser));
    }
    // 住所未設定の場合は設定
    if (registered) {
      try {
        const regObj = JSON.parse(registered);
        if (!regObj.address || regObj.address.trim() === '') {
          regObj.address = '東京都豊島区池袋4丁目';
          localStorage.setItem('registeredUser', JSON.stringify(regObj));
        }
      } catch (err) {}
    }
  } catch (e) {}

  // 古い実装で localStorage に残っている session user を削除
  try { localStorage.removeItem('user'); } catch(e){}

  const currentFile = location.pathname.split('/').pop();
  const unrestricted = ['index.html','signup.html',''];
  if (!unrestricted.includes(currentFile)) {
    requireLogin();
  }
  if (isLoggedIn()) {
    if (currentFile === 'index.html' || currentFile === '' || currentFile === '/') {
      location.href = 'map';
    }
    if (currentFile === 'signup.html') {
      location.href = 'map';
    }
  }

  const logoEl = document.querySelector('.small-logo');
  if (logoEl) {
    logoEl.style.cursor = 'pointer';
    logoEl.addEventListener('click', () => {
      if (isLoggedIn()) location.href = 'map';
      else location.href = 'login';
    });
  }
});

function highlightNavigation() {
  const navLinks = document.querySelectorAll('.bottom-nav .nav-item');
  if (!navLinks) return;
  const currentPage = location.pathname.split('/').pop();
  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPage) link.classList.add('active');
    else link.classList.remove('active');
  });
}

function isLoggedIn() {
  return !!sessionStorage.getItem('user');
}

function requireLogin() {
  if (!isLoggedIn()) {
    location.href = 'login';
  }
}
