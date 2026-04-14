document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const errorMsg = document.getElementById('error-message');
  const loginBtn = document.getElementById('login-btn');
  const btnText = loginBtn.querySelector('.btn-text');
  const loader = loginBtn.querySelector('.loader');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Reset error
    errorMsg.style.display = 'none';
    errorMsg.textContent = '';

    // Set loading state
    loginBtn.disabled = true;
    btnText.style.display = 'none';
    loader.style.display = 'block';

    // Prevent fetch if opened from file system
    if (window.location.protocol === 'file:') {
      showError('Cannot login: Please access this page through a local web server (e.g. http://localhost/kiosk pp/admin/login.html) instead of opening the file directly.');
      loginBtn.disabled = false;
      btnText.style.display = 'inline-block';
      loader.style.display = 'none';
      return;
    }

    const payload = {
      username: form.username.value.trim(),
      password: form.password.value,
      rememberMe: form.rememberMe.checked
    };

    try {
      const response = await fetch('../api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.success) {
        // Redirect to dashboard
        window.location.href = 'dashboard.html';
      } else {
        // Show error message
        showError(data.message || data.error || 'Login failed. Please try again.');
      }
    } catch (err) {
      console.error('Login error:', err);
      showError('Network error. Check your connection to the server or verify that Apache/MySQL are running in XAMPP.');
    } finally {
      // Revert loading state
      loginBtn.disabled = false;
      btnText.style.display = 'inline-block';
      loader.style.display = 'none';
    }
  });

  function showError(msg) {
    errorMsg.textContent = msg;
    errorMsg.style.display = 'block';

    // Trigger shake animation restart
    errorMsg.style.animation = 'none';
    errorMsg.offsetHeight; /* trigger reflow */
    errorMsg.style.animation = null;
  }
});
