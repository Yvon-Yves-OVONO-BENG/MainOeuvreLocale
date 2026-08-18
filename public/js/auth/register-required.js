document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('registerForm');
  const submitBtn = document.getElementById('submitRegisterBtn');

  const fields = [
    document.getElementById('register_type_compte'),
    document.getElementById('register_email'),
    document.getElementById('register_phone'),
    document.getElementById('register_password_first'),
    document.getElementById('register_password_second')
  ].filter(Boolean);

  function getBox(field) {
    if (!field) return null;

    if (field.id === 'register_phone') {
      return field.closest('.phone-field') || field.closest('.floating-field');
    }

    return field.closest('.floating-field') || field.parentElement;
  }

  function shake(field) {
    const box = getBox(field);

    field.classList.add('is-invalid');

    if (box) {
      box.classList.remove('field-vibrate');
      void box.offsetWidth;
      box.classList.add('field-vibrate');

      setTimeout(function () {
        box.classList.remove('field-vibrate');
      }, 450);
    }
  }

  function clearField(field) {
    field.classList.remove('is-invalid');

    const box = getBox(field);

    if (box) {
      box.classList.remove('field-vibrate');
    }
  }

  function isEmpty(field) {
    return !field.value || field.value.trim() === '';
  }

  function validateRequiredFields() {
    let valid = true;
    let firstInvalid = null;

    fields.forEach(function (field) {
      if (isEmpty(field)) {
        shake(field);
        firstInvalid = firstInvalid || field;
        valid = false;
      } else {
        clearField(field);
      }
    });

    const pwd1 = document.getElementById('register_password_first');
    const pwd2 = document.getElementById('register_password_second');

    if (pwd1 && pwd2 && pwd1.value.trim() && pwd2.value.trim() && pwd1.value !== pwd2.value) {
      shake(pwd1);
      shake(pwd2);
      firstInvalid = firstInvalid || pwd2;
      valid = false;
    }

    if (!valid) {
      if (navigator.vibrate) {
        navigator.vibrate([80, 40, 80]);
      }

      if (firstInvalid) {
        firstInvalid.focus();
      }
    }

    return valid;
  }

  fields.forEach(function (field) {
    field.addEventListener('input', function () {
      clearField(field);
    });

    field.addEventListener('change', function () {
      clearField(field);
    });
  });

  if (submitBtn) {
    submitBtn.addEventListener('click', function (event) {
      if (!validateRequiredFields()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      if (!validateRequiredFields()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
  }
});