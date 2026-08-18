// Toast auto-hide + close (sans dépendre d’un plugin)
    (function () {
        const wrap = document.getElementById('molToastWrap');
        if (! wrap) 
        return;


        wrap.querySelectorAll('[data-close="1"]').forEach(btn => {
            btn.addEventListener('click', () => {
            const toast = btn.closest('.mol-toast');
            if (toast) 
            toast.remove();

            });
        });

        wrap.querySelectorAll('.mol-toast[data-autohide="1"]').forEach(toast => {
            setTimeout(() => {
            if (!toast.isConnected) 
            return;

            toast.style.transition = 'opacity .25s ease, transform .25s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            setTimeout(() => toast.remove(), 260);
            }, 3200);
        });
    })();



    (function () {
        const bootJobValidation = function () {
            const form = document.querySelector('form[data-job-validation="1"]');

            if (!form) {
                return;
            }

            if (form.dataset.jobValidationReady === '1') {
                delete form.dataset.jobSubmitting;

                if (typeof form._molRefreshJobSubmit === 'function') {
                    form._molRefreshJobSubmit();
                }

                return;
            }

            const submitButton = form.querySelector('[data-job-submit]');
            const dateField = form.querySelector('[name$="[dateExpirationAt]"]');

            if (!submitButton) {
                return;
            }

            form.dataset.jobValidationReady = '1';

            const requiredFields = Array.from(
                form.querySelectorAll('input[required], select[required], textarea[required]')
            ).filter(function (field) {
                return field.type !== 'hidden' && !field.disabled;
            });

            const salaryMin = form.querySelector('[name$="[salaireMin]"]');
            const salaryMax = form.querySelector('[name$="[salaireMax]"]');

            const syncSalaryValidity = function () {
                if (!salaryMin || !salaryMax) {
                    return;
                }

                salaryMax.setCustomValidity('');

                if (salaryMin.value === '' || salaryMax.value === '') {
                    return;
                }

                if (Number(salaryMax.value) < Number(salaryMin.value)) {
                    salaryMax.setCustomValidity(
                        'Le salaire maximum doit être supérieur ou égal au salaire minimum.'
                    );
                }
            };

            const fieldMessage = function (field) {
                const scope = field.closest('[class*="col-"]') || field.parentElement;
                return scope ? scope.querySelector('[data-job-field-message]') : null;
            };

            const clearInvalidState = function (field) {
                field.classList.remove('mol-job-field-invalid', 'mol-job-field-shake');
                field.removeAttribute('aria-invalid');

                const feedback = fieldMessage(field);
                if (feedback) {
                    feedback.hidden = true;
                }
            };

            const showInvalidState = function (field, shake) {
                field.classList.add('mol-job-field-invalid');
                field.setAttribute('aria-invalid', 'true');

                const feedback = fieldMessage(field);
                if (feedback) {
                    feedback.hidden = false;
                }

                if (!shake) {
                    return;
                }

                field.classList.remove('mol-job-field-shake');
                void field.offsetWidth;
                field.classList.add('mol-job-field-shake');
            };

            const invalidFields = function () {
                syncSalaryValidity();

                return requiredFields.filter(function (field) {
                    return !field.validity.valid;
                });
            };

            const refreshSubmitButton = function () {
                const formIsValid = invalidFields().length === 0;

                submitButton.disabled = !formIsValid;
                submitButton.setAttribute('aria-disabled', formIsValid ? 'false' : 'true');
                submitButton.classList.toggle('mol-job-submit-disabled', !formIsValid);
            };

            form._molRefreshJobSubmit = refreshSubmitButton;

            requiredFields.forEach(function (field) {
                field.addEventListener('input', function () {
                    syncSalaryValidity();

                    if (field.validity.valid) {
                        clearInvalidState(field);
                    } else if (field.dataset.jobTouched === '1') {
                        showInvalidState(field, false);
                    }

                    if (field === salaryMin && salaryMax && salaryMax.dataset.jobTouched === '1') {
                        if (salaryMax.validity.valid) {
                            clearInvalidState(salaryMax);
                        } else {
                            showInvalidState(salaryMax, false);
                        }
                    }

                    refreshSubmitButton();
                });

                field.addEventListener('change', function () {
                    if (field.validity.valid) {
                        clearInvalidState(field);
                    }

                    refreshSubmitButton();
                });

                field.addEventListener('blur', function () {
                    field.dataset.jobTouched = '1';
                    syncSalaryValidity();

                    if (field.validity.valid) {
                        clearInvalidState(field);
                    } else {
                        showInvalidState(field, true);
                    }

                    refreshSubmitButton();
                });
            });

            form.addEventListener('invalid', function (event) {
                if (event.target instanceof HTMLElement) {
                    event.target.dataset.jobTouched = '1';
                    showInvalidState(event.target, true);
                }
            }, true);

            form.addEventListener('submit', function (event) {
                const invalid = invalidFields();

                if (invalid.length === 0 && form.dataset.jobSubmitting !== '1') {
                    form.dataset.jobSubmitting = '1';
                    submitButton.disabled = true;
                    submitButton.setAttribute('aria-disabled', 'true');
                    submitButton.classList.add('mol-job-submit-disabled');
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (form.dataset.jobSubmitting === '1') {
                    return;
                }

                invalid.forEach(function (field) {
                    field.dataset.jobTouched = '1';
                    showInvalidState(field, true);
                });

                invalid[0].focus({preventScroll: true});
                invalid[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                refreshSubmitButton();
            }, true);

            refreshSubmitButton();
        };

        bootJobValidation();

        if (!window.__molJobValidationTurboListener) {
            window.__molJobValidationTurboListener = true;
            document.addEventListener('turbo:load', bootJobValidation);
            window.addEventListener('pageshow', bootJobValidation);
        }
    })();
