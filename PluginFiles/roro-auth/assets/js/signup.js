(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('roro-signup-form');
    if (!form || !window.RORO_AUTH || !RORO_AUTH.rest) return;
    const errorBox = form.querySelector('.roro-auth-error');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (errorBox) errorBox.style.display = 'none';
      const username = form.querySelector('[name="username"]').value.trim();
      const email    = form.querySelector('[name="email"]').value.trim();
      const password = form.querySelector('[name="password"]').value;
      const display  = form.querySelector('[name="display_name"]').value.trim();
      if (!username || !email || !password) {
        if (errorBox) {
          errorBox.textContent = (RORO_AUTH.i18n && RORO_AUTH.i18n.error_required) || 'Missing required fields.';
          errorBox.style.display = 'block';
        }
        return;
      }
      try {
        const res = await fetch(RORO_AUTH.rest.register, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, email, password, display_name: display }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          if (errorBox) {
            errorBox.textContent = data.message || (RORO_AUTH.i18n && RORO_AUTH.i18n.signup_failed) || 'Registration failed.';
            errorBox.style.display = 'block';
          }
          return;
        }
        // Reload after sign up to become logged in
        window.location.reload();
      } catch (err) {
        if (errorBox) {
          errorBox.textContent = (RORO_AUTH.i18n && RORO_AUTH.i18n.signup_failed) || 'Registration failed.';
          errorBox.style.display = 'block';
        }
      }
    });
  });
})();