/* global wpRoro */
( () => {
	const btns = document.querySelectorAll( '.roro-spin' );
	btns.forEach( ( btn ) => {
		btn.addEventListener( 'click', async () => {
			btn.disabled = true;
			btn.textContent = '...';
			try {
				const res = await fetch( wpRoro.rest_url + 'gacha', {
					method: 'POST',
					headers: { 'X-WP-Nonce': wpRoro.nonce },
				} );
				const data = await res.json();
				alert( data.title || 'Got advice!' );
			} catch ( e ) {
				alert( 'Error :(' );
			}
			btn.disabled = false;
			btn.textContent = 'Spin!';
		} );
	} );
} )();
