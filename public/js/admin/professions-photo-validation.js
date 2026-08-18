// Validation visuelle du champ photo des professions.
(function (window, document, $) {
    'use strict';

    var MAX_SIZE = 2 * 1024 * 1024;
    var ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    var originalSaveProfession = null;

    function elements() {
        return {
            form: document.getElementById('professionForm'),
            id: document.getElementById('professionId'),
            input: document.getElementById('professionPhoto'),
            zone: document.getElementById('photoUploadZone'),
            error: document.getElementById('professionPhotoError'),
            preview: document.getElementById('photoPreview'),
            previewContainer: document.getElementById('photoPreviewContainer')
        };
    }

    function clearPhotoError() {
        var el = elements();

        if (el.zone) {
            el.zone.classList.remove('is-invalid');
        }

        if (el.input) {
            el.input.setAttribute('aria-invalid', 'false');
        }

        if (el.error) {
            el.error.classList.remove('is-visible');
            el.error.textContent = '';
        }
    }

    function showPhotoError(message) {
        var el = elements();

        if (el.zone) {
            el.zone.classList.remove('is-invalid');
            void el.zone.offsetWidth;
            el.zone.classList.add('is-invalid');
            el.zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        if (el.input) {
            el.input.setAttribute('aria-invalid', 'true');
        }

        if (el.error) {
            el.error.innerHTML = '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span>' +
                String(message) + '</span>';
            el.error.classList.add('is-visible');
        }
    }

    function hasExistingPhoto(el) {
        if (!el.id || !String(el.id.value || '').trim()) {
            return false;
        }

        return Boolean(
            el.preview &&
            String(el.preview.getAttribute('src') || '').trim() &&
            el.previewContainer &&
            window.getComputedStyle(el.previewContainer).display !== 'none'
        );
    }

    function validatePhoto(showMessage) {
        var el = elements();
        var file;
        var message = '';

        if (!el.input) {
            return true;
        }

        file = el.input.files && el.input.files.length
            ? el.input.files[0]
            : null;

        if (!file && !hasExistingPhoto(el)) {
            message = 'Veuillez ajouter une photo pour cette profession.';
        } else if (file && file.size > MAX_SIZE) {
            message = 'La photo ne doit pas dépasser 2 Mo.';
        } else if (file && ALLOWED_TYPES.indexOf(file.type) === -1) {
            message = 'Format non autorisé. Utilisez une image JPG, PNG ou GIF.';
        }

        if (message) {
            if (showMessage !== false) {
                showPhotoError(message);
            }
            return false;
        }

        clearPhotoError();
        return true;
    }

    function wrapSaveProfession() {
        if (
            typeof window.saveProfession !== 'function' ||
            window.saveProfession.__molPhotoValidationWrapped
        ) {
            return;
        }

        originalSaveProfession = window.saveProfession;

        window.saveProfession = function (event) {
            if (!validatePhoto(true)) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                return false;
            }

            return originalSaveProfession.apply(this, arguments);
        };

        window.saveProfession.__molPhotoValidationWrapped = true;
    }

    function wrapModalFunctions() {
        ['openCreateModal', 'openEditModal', 'closeModal'].forEach(function (name) {
            var original = window[name];

            if (typeof original !== 'function' || original.__molPhotoValidationWrapped) {
                return;
            }

            window[name] = function () {
                clearPhotoError();
                return original.apply(this, arguments);
            };
            window[name].__molPhotoValidationWrapped = true;
        });
    }

    function boot() {
        var el = elements();

        if (!el.form || el.form.dataset.photoValidationReady === '1') {
            wrapSaveProfession();
            wrapModalFunctions();
            return;
        }

        el.form.dataset.photoValidationReady = '1';

        if (el.input) {
            el.input.addEventListener('change', function () {
                validatePhoto(true);
            });
        }

        if (el.zone && el.input) {
            el.zone.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    el.input.click();
                }
            });
        }

        /*
         * Le script principal appelle window.saveProfession depuis son
         * listener natif. Le wrapper ci-dessus garantit donc la validation
         * même lorsque le formulaire est soumis avec Entrée.
         */
        wrapSaveProfession();
        wrapModalFunctions();

        if ($) {
            $(document)
                .off('ajaxError.professionPhoto')
                .on('ajaxError.professionPhoto', function (_event, xhr, settings) {
                    var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
                    var url = settings && settings.url ? String(settings.url) : '';

                    if (
                        response &&
                        (response.field === 'photo' || (response.errors && response.errors.photo)) &&
                        url.indexOf('/admin/professions') !== -1
                    ) {
                        showPhotoError(
                            (response.errors && response.errors.photo) ||
                            response.message ||
                            'La photo sélectionnée est invalide.'
                        );
                    }
                });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.setTimeout(boot, 0);
    });
    document.addEventListener('turbo:load', function () {
        window.setTimeout(boot, 0);
    });
})(window, document, window.jQuery);
