(() => {
    'use strict';

    const initializeAnnonceSelector = () => {
        document.querySelectorAll('[data-annonce-auto-select]').forEach((form) => {
            if (form.dataset.initialized === 'true') {
                return;
            }

            const select = form.querySelector('[data-annonce-select]');
            const loading = form.querySelector('[data-annonce-loading]');

            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            form.dataset.initialized = 'true';

            select.addEventListener('change', () => {
                if (form.classList.contains('is-loading')) {
                    return;
                }

                form.classList.add('is-loading');
                form.setAttribute('aria-busy', 'true');
                select.setAttribute('aria-disabled', 'true');

                if (loading instanceof HTMLElement) {
                    loading.hidden = false;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            });
        });
    };

    const initializeBoostForms = () => {
        document.querySelectorAll('[data-boost-form]').forEach((form) => {
            if (form.dataset.initialized === 'true') {
                return;
            }

            const button = form.querySelector('button[type="submit"]');
            const label = form.querySelector('[data-button-label]');
            const loading = form.querySelector('[data-button-loading]');

            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            form.dataset.initialized = 'true';

            form.addEventListener('submit', () => {
                if (button.disabled || button.classList.contains('is-loading')) {
                    return;
                }

                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
                button.disabled = true;

                if (label instanceof HTMLElement) {
                    label.hidden = true;
                }

                if (loading instanceof HTMLElement) {
                    loading.hidden = false;
                }
            });
        });
    };

    const initialize = () => {
        initializeAnnonceSelector();
        initializeBoostForms();
    };

    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('turbo:load', initialize);
})();
