(function() {
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }

    async function fetchToday() {
        const loading  = $('.roro-rec-loading');
        const errorEl  = $('.roro-rec-error');
        const emptyEl  = $('.roro-rec-empty');
        const content  = $('.roro-rec-content');
        loading.style.display  = 'block';
        errorEl.style.display  = 'none';
        emptyEl.style.display  = 'none';
        content.style.display  = 'none';
        try {
            const res = await fetch(roroRecommend.restBase + '/recommend/today?lang=' + encodeURIComponent(roroRecommend.lang), {
                headers: { 'X-WP-Nonce': roroRecommend.nonce }
            });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            const data = await res.json();
            // おすすめデータが無い場合は空メッセージを表示
            if (!data || !data.spot || !data.advice) {
                loading.style.display = 'none';
                content.style.display = 'none';
                errorEl.style.display = 'none';
                emptyEl.style.display = 'block';
                return;
            }
            // 取得したデータを各要素に表示
            $('.roro-rec-spot-name').textContent    = data.spot.name || '';
            $('.roro-rec-spot-address').textContent = data.spot.address || '';
            $('.roro-rec-spot-desc').textContent    = data.spot.desc || '';
            $('.roro-rec-advice-text').textContent  = data.advice.text || '';
            // お気に入りボタンにスポットIDを設定
            const favBtn = $('.roro-rec-fav');
            if (favBtn) {
                favBtn.dataset.spotId = data.spot.id;
            }
            // コンテンツ表示に切り替え
            loading.style.display = 'none';
            content.style.display = 'block';
        } catch (e) {
            console.error(e);
            loading.style.display = 'none';
            errorEl.style.display = 'block';
        }
    }

    async function regen() {
        const refreshBtn = $('.roro-rec-refresh');
        refreshBtn.disabled = true;
        try {
            const res = await fetch(roroRecommend.restBase + '/recommend/regen', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': roroRecommend.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ lang: roroRecommend.lang })
            });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            // 新しいレコメンド取得後に表示を更新
            await fetchToday();
        } catch (e) {
            console.error(e);
            alert(roroRecommend.i18n.error_generic || 'Error');
        } finally {
            refreshBtn.disabled = false;
        }
    }

    function addFavorite() {
        const spotId = this.dataset.spotId;
        if (!spotId) return;
        // お気に入りプラグイン (roro-favorites) が導入されていれば、以下のクエリでスポットをお気に入り登録
        const url = new URL(window.location.href);
        url.searchParams.set('roro_fav_add', 'spot');
        url.searchParams.set('spot_id', spotId);
        window.location.href = url.toString();
    }

    // ボタンクリックイベントの委譲処理
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('roro-rec-refresh')) {
            regen();
        }
        if (e.target && e.target.classList.contains('roro-rec-fav')) {
            addFavorite.call(e.target);
        }
    });

    // 初期表示時に本日のおすすめを取得
    document.addEventListener('DOMContentLoaded', fetchToday);
})();
