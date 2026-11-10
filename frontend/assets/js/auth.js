(function () {
  const query = new URLSearchParams(window.location.search);
  const verifyEmail = query.get('email') || sessionStorage.getItem('grace_verify_email') || '';
  sessionStorage.removeItem('grace_development_otp');
  document.querySelectorAll('[data-verify-email]').forEach(function (element) {
    if (element.matches('input')) element.value = verifyEmail;
    else element.textContent = verifyEmail || 'your email address';
  });

  function getAlert(form) {
    return form.querySelector('[data-alert]');
  }

  function showAlert(form, message, success) {
    const alert = getAlert(form);
    if (!alert) return;
    alert.textContent = message;
    alert.classList.toggle('alert-success', Boolean(success));
    alert.hidden = false;
  }

  function clearAlert(form) {
    const alert = getAlert(form);
    if (alert) {
      alert.hidden = true;
      alert.textContent = '';
      alert.classList.remove('alert-success');
    }
  }

  function setBusy(form, busy, label) {
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;
    if (busy) {
      button.dataset.originalText = button.innerHTML;
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      if (button.dataset.originalText) button.innerHTML = button.dataset.originalText;
    }
  }

  document.querySelectorAll('[data-auth-form]').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      clearAlert(form);
      if (!form.reportValidity()) return;

      const action = form.dataset.authForm;
      const endpoints = { login: 'login.php', register: 'register.php', verify: 'verify_otp.php' };
      const values = Object.fromEntries(new FormData(form).entries());
      setBusy(form, true, action === 'verify' ? 'Verifying…' : 'Please wait…');
      try {
        if (action === 'verify') values.email = verifyEmail;
        const payload = await window.GraceApi.post(endpoints[action], values);
        if (action === 'register') {
          sessionStorage.setItem('grace_verify_email', values.email);
          window.location.href = 'verify.html?email=' + encodeURIComponent(values.email);
        } else if (action === 'verify') {
          sessionStorage.removeItem('grace_verify_email');
          window.location.href = 'index.html?verified=1';
        } else {
          window.location.href = 'dashboard.html';
        }
        return payload;
      } catch (error) {
        if (action === 'register' && error.status === 502) {
          showAlert(form, error.message);
          return;
        }
        if (error.payload && error.payload.needs_verification) {
          sessionStorage.setItem('grace_verify_email', values.email);
          window.location.href = 'verify.html?email=' + encodeURIComponent(values.email);
          return;
        }
        showAlert(form, error.message || 'Unable to continue. Please try again.');
      } finally {
        setBusy(form, false);
      }
    });
  });

  const loginForm = document.querySelector('[data-auth-form="login"]');
  if (loginForm && query.get('verified') === '1') {
    showAlert(loginForm, 'Your email is verified. You can now sign in.', true);
  }

  const resendButton = document.querySelector('[data-resend]');
  if (resendButton) {
    resendButton.addEventListener('click', async function () {
      const form = resendButton.closest('form');
      clearAlert(form);
      resendButton.disabled = true;
      try {
        await window.GraceApi.post('resend_otp.php', { email: verifyEmail });
        showAlert(form, 'A new verification code has been sent.', true);
      } catch (error) {
        showAlert(form, error.message || 'Unable to send a new code.');
      } finally {
        window.setTimeout(function () { resendButton.disabled = false; }, 2500);
      }
    });
  }
})();