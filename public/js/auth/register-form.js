document.addEventListener('DOMContentLoaded', function () {
  function byId() {
    for (let i = 0; i < arguments.length; i++) {
      const el = document.getElementById(arguments[i]);
      if (el) return el;
    }

    return null;
  }

  const form = document.getElementById('registerForm');
  const submitBtn = document.getElementById('submitRegisterBtn');

  const typeCompte = byId('register_type_compte');
  const emailInput = byId('register_email');
  const phoneInput = byId('register_phone');

  const pwd1 = byId('register_password_first');
  const pwd2 = byId('register_password_second');

  const countryCode = document.getElementById('countryCode');

  const emailField = document.getElementById('emailField');
  const emailFeedback = document.getElementById('emailFeedback');
  const checkUrl = emailField ? emailField.dataset.checkUrl : null;

  const meterBar = document.getElementById('passwordMeterBar');
  const meterText = document.getElementById('passwordMeterText');

  let emailTimer = null;
  let lastCheckedEmail = '';
  let emailState = 'empty';

  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/;
  const phoneRegex = /^[+]?[\d\s().-]{6,30}$/;

  function getFieldBox(input) {
    if (!input) return null;

    return input.closest('.floating-field') ||
      input.closest('.phone-field') ||
      input.parentElement;
  }

  function vibrateField(input) {
    if (!input) return;

    const box = getFieldBox(input);

    if (!box) return;

    box.classList.remove('field-vibrate');
    void box.offsetWidth;
    box.classList.add('field-vibrate');

    input.classList.add('is-invalid');

    setTimeout(function () {
      box.classList.remove('field-vibrate');
    }, 420);
  }

  function clearInvalidField(input) {
    if (!input) return;

    input.classList.remove('is-invalid');

    const box = getFieldBox(input);

    if (box) {
      box.classList.remove('field-vibrate');
    }
  }

  function vibrateDevice() {
    if (navigator.vibrate) {
      navigator.vibrate([70, 40, 70]);
    }
  }

  function refreshFloatingFields() {
    document.querySelectorAll('.floating-field').forEach(function (field) {
      const input = field.querySelector('.form-control, .form-select');

      if (!input) return;

      function refresh() {
        field.classList.toggle('has-value', (input.value || '').trim() !== '');
      }

      refresh();

      input.addEventListener('input', refresh);
      input.addEventListener('change', refresh);
    });
  }

  function normalizePhoneBeforeSubmit() {
    if (!phoneInput || !countryCode) return;

    const value = phoneInput.value.trim();

    if (!value) return;
    if (value.startsWith('+')) return;

    phoneInput.value = countryCode.value + ' ' + value.replace(/^0+/, '');
  }

  function resetEmailClasses() {
    if (!emailField || !emailFeedback) return;

    emailField.classList.remove('email-valid', 'email-invalid', 'email-checking');
    emailFeedback.classList.remove('is-valid', 'is-invalid', 'is-checking');
  }

  function setEmailMessage(state, message) {
    emailState = state;

    if (!emailField || !emailFeedback) return;

    resetEmailClasses();
    emailFeedback.textContent = message || '';

    if (state === 'checking') {
      emailField.classList.add('email-checking');
      emailFeedback.classList.add('is-checking');
    }

    if (state === 'available') {
      emailField.classList.add('email-valid');
      emailFeedback.classList.add('is-valid');
    }

    if (state === 'invalid' || state === 'used' || state === 'error') {
      emailField.classList.add('email-invalid');
      emailFeedback.classList.add('is-invalid');
    }
  }

  function validateFields() {
    let ok = true;
    let firstInvalid = null;

    const typeValue = (typeCompte ? typeCompte.value : '').trim();
    const emailValue = (emailInput ? emailInput.value : '').trim();
    const phoneValue = (phoneInput ? phoneInput.value : '').trim();
    const pwd1Value = (pwd1 ? pwd1.value : '').trim();
    const pwd2Value = (pwd2 ? pwd2.value : '').trim();

    if (!typeValue) {
      vibrateField(typeCompte);
      firstInvalid = firstInvalid || typeCompte;
      ok = false;
    } else {
      clearInvalidField(typeCompte);
    }

    if (!emailValue) {
      vibrateField(emailInput);
      setEmailMessage('invalid', 'Email requis');
      firstInvalid = firstInvalid || emailInput;
      ok = false;
    } else if (!emailRegex.test(emailValue)) {
      vibrateField(emailInput);
      setEmailMessage('invalid', 'Email non valide');
      firstInvalid = firstInvalid || emailInput;
      ok = false;
    } else {
      clearInvalidField(emailInput);
    }

    if (!phoneValue) {
      vibrateField(phoneInput);
      firstInvalid = firstInvalid || phoneInput;
      ok = false;
    } else if (!phoneRegex.test(phoneValue)) {
      vibrateField(phoneInput);
      firstInvalid = firstInvalid || phoneInput;
      ok = false;
    } else {
      clearInvalidField(phoneInput);
    }

    if (!pwd1Value) {
      vibrateField(pwd1);
      firstInvalid = firstInvalid || pwd1;
      ok = false;
    } else {
      clearInvalidField(pwd1);
    }

    if (!pwd2Value) {
      vibrateField(pwd2);
      firstInvalid = firstInvalid || pwd2;
      ok = false;
    } else {
      clearInvalidField(pwd2);
    }

    if (pwd1Value && pwd2Value && pwd1Value !== pwd2Value) {
      vibrateField(pwd1);
      vibrateField(pwd2);
      firstInvalid = firstInvalid || pwd2;
      ok = false;
    }

    if (!ok) {
      vibrateDevice();

      if (firstInvalid) {
        firstInvalid.focus();
      }
    }

    return ok;
  }

  function checkEmailAvailability(callback) {
    if (!emailInput || !checkUrl) {
      setEmailMessage('error', 'Vérification impossible');
      if (typeof callback === 'function') callback(false);
      return;
    }

    const email = emailInput.value.trim().toLowerCase();

    clearTimeout(emailTimer);

    if (!email) {
      lastCheckedEmail = '';
      setEmailMessage('empty', '');
      if (typeof callback === 'function') callback(false);
      return;
    }

    if (!emailRegex.test(email)) {
      lastCheckedEmail = '';
      setEmailMessage('invalid', 'Email non valide');
      if (typeof callback === 'function') callback(false);
      return;
    }

    if (lastCheckedEmail === email && emailState === 'available') {
      if (typeof callback === 'function') callback(true);
      return;
    }

    if (lastCheckedEmail === email && emailState === 'used') {
      if (typeof callback === 'function') callback(false);
      return;
    }

    setEmailMessage('checking', 'Vérification...');

    emailTimer = setTimeout(function () {
      fetch(checkUrl + '?email=' + encodeURIComponent(email), {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (response) {
          return response.text().then(function (text) {
            let data;

            try {
              data = JSON.parse(text);
            } catch (e) {
              throw new Error('JSON invalide');
            }

            if (!response.ok) {
              throw new Error(data.message || 'Erreur HTTP ' + response.status);
            }

            return data;
          });
        })
        .then(function (data) {
          lastCheckedEmail = email;

          if (data.exists === true || Number(data.count) > 0) {
            setEmailMessage('used', data.message || 'Email déjà utilisé');
            if (typeof callback === 'function') callback(false);
            return;
          }

          setEmailMessage('available', data.message || 'Email disponible');
          if (typeof callback === 'function') callback(true);
        })
        .catch(function () {
          lastCheckedEmail = '';
          setEmailMessage('error', 'Vérification impossible');
          if (typeof callback === 'function') callback(false);
        });
    }, 350);
  }

  function initPasswordToggle(input, buttonId, eyeId, eyeOffId) {
    const button = document.getElementById(buttonId);
    const eye = document.getElementById(eyeId);
    const eyeOff = document.getElementById(eyeOffId);

    if (!input || !button || !eye || !eyeOff) return;

    button.addEventListener('click', function () {
      const isPassword = input.type === 'password';

      input.type = isPassword ? 'text' : 'password';

      eye.classList.toggle('d-none', isPassword);
      eyeOff.classList.toggle('d-none', !isPassword);

      button.setAttribute(
        'aria-label',
        isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
      );
    });
  }

  function renderPasswordStrength() {
    if (!pwd1 || !meterBar || !meterText) return;

    const value = pwd1.value || '';
    let score = 0;

    if (value.length >= 8) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    if (value.length >= 12) score++;

    meterText.classList.remove('is-weak', 'is-medium', 'is-strong');

    if (!value) {
      meterBar.style.width = '0%';
      meterBar.style.background = '#64748b';
      meterText.textContent = 'Rigidité du mot de passe : non définie';
      return;
    }

    if (score <= 1) {
      meterBar.style.width = '25%';
      meterBar.style.background = '#ef4444';
      meterText.textContent = 'Rigidité du mot de passe : faible';
      meterText.classList.add('is-weak');
      return;
    }

    if (score <= 3) {
      meterBar.style.width = '60%';
      meterBar.style.background = '#eab308';
      meterText.textContent = 'Rigidité du mot de passe : moyenne';
      meterText.classList.add('is-medium');
      return;
    }

    meterBar.style.width = '100%';
    meterBar.style.background = '#22c55e';
    meterText.textContent = 'Rigidité du mot de passe : forte';
    meterText.classList.add('is-strong');
  }

  refreshFloatingFields();

  initPasswordToggle(pwd1, 'togglePasswordFirst', 'iconEyeFirst', 'iconEyeOffFirst');
  initPasswordToggle(pwd2, 'togglePasswordSecond', 'iconEyeSecond', 'iconEyeOffSecond');

  if (pwd1) {
    pwd1.addEventListener('input', renderPasswordStrength);
    renderPasswordStrength();
  }

  if (emailInput) {
    emailInput.addEventListener('input', function () {
      emailInput.classList.remove('is-invalid');
      lastCheckedEmail = '';
      clearTimeout(emailTimer);

      const value = emailInput.value.trim().toLowerCase();

      if (!value) {
        setEmailMessage('empty', '');
        return;
      }

      if (!emailRegex.test(value)) {
        setEmailMessage('invalid', 'Email non valide');
        return;
      }

      checkEmailAvailability();
    });

    emailInput.addEventListener('blur', function () {
      checkEmailAvailability();
    });
  }

  [typeCompte, emailInput, phoneInput, pwd1, pwd2].forEach(function (el) {
    if (!el) return;

    el.addEventListener('input', function () {
      clearInvalidField(el);
    });

    el.addEventListener('change', function () {
      clearInvalidField(el);
    });
  });

  if (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.ready === '1') {
        return;
      }

      event.preventDefault();

      if (!validateFields()) {
        return;
      }

      normalizePhoneBeforeSubmit();

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Vérification...';
      }

      checkEmailAvailability(function (emailOk) {
        if (!emailOk) {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Créer mon compte';
          }

          if (emailInput) {
            emailInput.focus();
          }

          return;
        }

        form.dataset.ready = '1';
        form.submit();
      });
    });
  }
});