(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('roro-login-form');
    if (!form || !window.RORO_AUTH || !RORO_AUTH.rest) return;
    const errorBox = form.querySelector('.roro-auth-error');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      // Hide previous error
      if (errorBox) errorBox.style.display = 'none';
      const username = form.querySelector('[name="username"]').value.trim();
      const password = form.querySelector('[name="password"]').value;
      if (!username || !password) {
        if (errorBox) {
          errorBox.textContent = (RORO_AUTH.i18n && RORO_AUTH.i18n.error_required) || 'Missing required fields.';
          errorBox.style.display = 'block';
        }
        return;
      }
      try {
        const res = await fetch(RORO_AUTH.rest.login, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          if (errorBox) {
            errorBox.textContent = data.message || (RORO_AUTH.i18n && RORO_AUTH.i18n.error_login_failed) || 'Login failed.';
            errorBox.style.display = 'block';
          }
          return;
        }
        // Reload the page on successful login
        window.location.reload();
      } catch (err) {
        if (errorBox) {
          errorBox.textContent = (RORO_AUTH.i18n && RORO_AUTH.i18n.error_login_failed) || 'Login failed.';
          errorBox.style.display = 'block';
        }
      }
    });
  });
})();