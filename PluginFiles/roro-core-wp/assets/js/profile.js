/*
 * roro-profile.js – マイページ編集処理
 */
document.addEventListener('DOMContentLoaded', () => {
  requireLogin(); // 未ログインならリダイレクト

  const userData = JSON.parse(sessionStorage.getItem('user')) || {};

  const nameEl     = document.getElementById('profile-name');
  const locationEl = document.getElementById('profile-location');
  const favCountEl = document.getElementById('fav-count');

  const nameInput     = document.getElementById('profile-name-input');
  const furiganaInput = document.getElementById('profile-furigana-input');
  const emailInput    = document.getElementById('profile-email');
  const phoneInput    = document.getElementById('profile-phone');
  const addressInput  = document.getElementById('profile-address');
  const petsContainer = document.getElementById('pets-container');
  const addPetBtn     = document.getElementById('add-pet-btn');
  const languageSelect= document.getElementById('profile-language');

  // 初期表示
  nameEl.textContent    = userData.name || 'ゲストユーザー';
  locationEl.textContent= userData.address || '';
  favCountEl.textContent= (JSON.parse(localStorage.getItem('favorites')) || []).length;
  document.getElementById('followers').textContent = 0;
  document.getElementById('following').textContent = 0;

  nameInput.value     = userData.name || '';
  furiganaInput.value = userData.furigana || '';
  emailInput.value    = userData.email || '';
  phoneInput.value    = userData.phone || '';
  addressInput.value  = userData.address || '';
  languageSelect.value= userData.language || getUserLang();

  // ペット情報の描画
  let pets = [];
  if (Array.isArray(userData.pets)) pets = userData.pets;
  function renderPets() {
    petsContainer.innerHTML = '';
    pets.forEach((pet, index) => {
      const wrap = document.createElement('div');
      wrap.className = 'pet-item';

      const typeSel = document.createElement('select');
      typeSel.innerHTML = `<option value="dog">犬</option><option value="cat">猫</option><option value="other">その他</option>`;
      typeSel.value = pet.type || 'dog';

      const breedSel = document.createElement('select');
      const dogBreeds = ['', 'トイ・プードル', 'チワワ', '柴', 'ミニチュア・ダックスフンド'];
      dogBreeds.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b; opt.textContent = b || '犬種を選択';
        breedSel.appendChild(opt);
      });
      breedSel.value = pet.breed || '';
      breedSel.disabled = (pet.type !== 'dog');

      const nameInput = document.createElement('input');
      nameInput.type = 'text'; nameInput.value = pet.name || ''; nameInput.placeholder = '名前';

      const ageSel = document.createElement('select');
      ageSel.innerHTML = `<option value="puppy">子犬/子猫</option><option value="adult">成犬/成猫</option><option value="senior">シニア</option>`;
      ageSel.value = pet.age || 'puppy';

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button'; removeBtn.className = 'btn danger-btn'; removeBtn.textContent = '削除';
      removeBtn.addEventListener('click', () => {
        pets.splice(index, 1);
        renderPets();
      });

      typeSel.addEventListener('change', () => {
        if (typeSel.value === 'dog') breedSel.disabled = false;
        else { breedSel.disabled = true; breedSel.value = ''; }
      });

      wrap.append(typeSel, breedSel, nameInput, ageSel, removeBtn);
      petsContainer.appendChild(wrap);
    });
  }
  renderPets();

  addPetBtn?.addEventListener('click', () => {
    pets.push({ type:'dog', breed:'', name:'', age:'puppy' });
    renderPets();
  });

  // 保存処理
  document.getElementById('profile-form').addEventListener('submit', (e) => {
    e.preventDefault();
    userData.email = emailInput.value.trim();
    userData.phone = phoneInput.value.trim();
    userData.address = addressInput.value.trim();
    userData.language = languageSelect.value;

    // pets をDOMから再構築
    const newPets = [];
    petsContainer.querySelectorAll('.pet-item').forEach(div => {
      const [typeSel, breedSel, nameInput, ageSel] = div.querySelectorAll('select, input');
      newPets.push({ type: typeSel.value, breed: breedSel.value, name: nameInput.value.trim(), age: ageSel.value });
    });
    userData.pets = newPets;

    sessionStorage.setItem('user', JSON.stringify(userData));
    try {
      const reg = JSON.parse(localStorage.getItem('registeredUser'));
      if (reg) {
        localStorage.setItem('registeredUser', JSON.stringify({ ...reg, ...userData }));
      }
    } catch(e){}
    // 言語切替
    setUserLang(userData.language);
    location.reload();
  });

  // ログアウト処理
  document.getElementById('logout-btn')?.addEventListener('click', () => {
    sessionStorage.removeItem('user');
    location.href = '/login';
  });
});
