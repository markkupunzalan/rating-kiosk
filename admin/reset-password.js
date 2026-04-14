document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('reset-password-form');
  const errorMsg = document.getElementById('error-message');
  const successMsg = document.getElementById('success-message');
  const submitBtn = document.getElementById('submit-btn');
  const btnText = submitBtn.querySelector('.btn-text');
  const loader = submitBtn.querySelector('.loader');
  const loginLink = document.getElementById('login-link');
  const tokenInput = document.getElementById('token');

  // Extract token from URL
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');

  if (!token) {
    showError('Invalid or missing password reset token.');
    form.querySelector('input[type="password"]').disabled = true;
    form.querySelector('#confirm-password').disabled = true;
    submitBtn.disabled = true;
    loginLink.style.display = 'inline-flex';
  } else {
    tokenInput.value = token;
  }

  function showError(msg) {
    errorMsg.style.display = 'block';
    errorMsg.textContent = msg;
    successMsg.style.display = 'none';
  }

  function showSuccess(msg) {
    successMsg.style.display = 'block';
    successMsg.textContent = msg;
    errorMsg.style.display = 'none';
  }

  function setLoading(isLoading) {
    submitBtn.disabled = isLoading;
    if (isLoading) {
      btnText.style.display = 'none';
      loader.style.display = 'inline-block';
    } else {
      btnText.style.display = 'inline-block';
      loader.style.display = 'none';
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorMsg.style.display = 'none';
    successMsg.style.display = 'none';

    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    if (password !== confirmPassword) {
      showError('Passwords do not match');
      return;
    }
    if (password.length < 8) {
      showError('Password must be at least 8 characters long');
      return;
    }

    setLoading(true);

    try {
      const formData = new URLSearchParams();
      formData.append('token', tokenInput.value);
      formData.append('password', password);

      const response = await fetch('../api/reset_password.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
      });

      const textResponse = await response.text();
      let res;
      try {
        res = JSON.parse(textResponse);
      } catch (err) {
        showError('A server error occurred. Please try again.');
        return;
      }

      if (res.success) {
        showSuccess(res.message);
        form.reset();
        submitBtn.style.display = 'none';
        loginLink.style.display = 'inline-flex';
      } else {
        showError(res.error || 'Failed to reset password');
      }

    } catch (error) {
      showError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  });
});

