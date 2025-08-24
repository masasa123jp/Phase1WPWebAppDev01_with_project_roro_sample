(function() {
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function el(tag, className) { var x = document.createElement(tag); if(className) x.className = className; return x; }

    async function loadList() {
        const empty = $('.roro-fav-empty');
        const list = $('.roro-fav-list');
        list.innerHTML = '';
        empty.style.display = 'none';
        try {
            const res = await fetch(roroFavorites.restBase + '/favorites', {
                headers: { 'X-WP-Nonce': roroFavorites.nonce }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            const items = data.items || [];
            if (items.length === 0) {
                // お気に入りがない場合
                empty.style.display = 'block';
                return;
            }
            items.forEach(function(it) {
                const li = el('li', 'roro-fav-item');
                const left = el('div');
                const title = el('div');
                const meta = el('div', 'roro-fav-meta');
                const type = el('span', 'roro-fav-type');
                type.textContent = (it.target_type === 'spot' ? 'SPOT' : 'EVENT') + ' • ';
                title.appendChild(type);
                title.appendChild(document.createTextNode(it.name || '(no name)'));
                meta.textContent = (it.address || '') + (it.description ? ' — ' + it.description : '');
                left.appendChild(title);
                left.appendChild(meta);

                const actions = el('div', 'roro-fav-actions');
                const rm = el('button', 'button-link');
                rm.textContent = roroFavorites.i18n.btn_remove;
                rm.addEventListener('click', () => removeItem(it.target_type, it.target_id));
                actions.appendChild(rm);

                li.appendChild(left);
                li.appendChild(actions);
                list.appendChild(li);
            });
        } catch(e) {
            console.error(e);
            alert(roroFavorites.i18n.error_generic || 'Error loading favorites.');
        }
    }

    async function removeItem(type, id) {
        try {
            const res = await fetch(roroFavorites.restBase + '/favorites/remove?target_type=' + encodeURIComponent(type) + '&target_id=' + encodeURIComponent(id), {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': roroFavorites.nonce }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await loadList();  // 更新後リロード
        } catch(e) {
            console.error(e);
            alert(roroFavorites.i18n.error_generic || 'Error processing request.');
        }
    }

    // ページ読み込み時、お気に入りリスト読み込みを開始
    document.addEventListener('DOMContentLoaded', loadList);
})();
