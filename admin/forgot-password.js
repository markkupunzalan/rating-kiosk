document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('forgot-password-form');
  const errorMsg = document.getElementById('error-message');
  const successMsg = document.getElementById('success-message');
  const submitBtn = document.getElementById('submit-btn');
  const btnText = submitBtn.querySelector('.btn-text');
  const loader = submitBtn.querySelector('.loader');

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

    const email = document.getElementById('email').value.trim();

    if (!email) {
      showError('Please enter your email address');
      return;
    }

    setLoading(true);

    try {
      const response = await fetch('../api/forgot_password.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ email })
      });

      // Handle potential HTML response gracefully instead of crashing
      const textResponse = await response.text();
      let res;
      try {
        res = JSON.parse(textResponse);
      } catch (err) {
        showError('A server error occurred. Please try again.');
        console.error('Server returned non-JSON:', textResponse);
        return;
      }

      if (res.success) {
        showSuccess(res.message);
        form.reset();
      } else {
        showError(res.error || 'Failed to send reset link');
      }

    } catch (error) {
      showError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  });
});
