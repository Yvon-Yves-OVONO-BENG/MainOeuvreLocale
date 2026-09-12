(() => {
    'use strict';
    // Active la validation à la soumission : un champ incorrect ne condamne jamais le bouton au gris.
    function initializeJobEdit() {
        const form = document.querySelector('form[data-job-validation="1"]');
        if (!form || form.dataset.editValidationReady) return;
        form.dataset.editValidationReady = '1';
        const button = form.querySelector('[data-job-submit]');
        if (!button) return;
        form.noValidate = true;
        const original = button.innerHTML;
        const summary = document.createElement('div');
        summary.className = 'alert alert-danger';
        summary.setAttribute('role', 'alert');
        summary.tabIndex = -1;
        summary.hidden = true;
        form.prepend(summary);
        let submitting = false;

        // Restaure le formulaire lors d'un retour navigateur ou après une erreur de validation.
        function resetButton() {
            submitting = false;
            button.disabled = false;
            button.removeAttribute('aria-disabled');
            button.removeAttribute('aria-busy');
            button.classList.remove('mol-job-submit-disabled');
            button.innerHTML = original;
        }
        // Valide les champs rendus et la cohérence des montants, sans imposer la note facultative.
        function validate() {
            const minimum = form.querySelector('[name$="[salaireMin]"]');
            const maximum = form.querySelector('[name$="[salaireMax]"]');
            if (maximum) {
                maximum.setCustomValidity(minimum && minimum.value !== '' && maximum.value !== '' && Number(minimum.value) > Number(maximum.value)
                    ? 'Le salaire maximum doit être supérieur ou égal au minimum.' : '');
            }
            const errors = [];
            for (const field of form.elements) {
                if (!field.willValidate) continue;
                let valid = field.checkValidity();
                // minlength doit aussi être contrôlé pour les anciennes valeurs préremplies.
                const minLength = Number(field.getAttribute('minlength') || 0);
                const tooShort = minLength > 0 && field.value.trim().length < minLength;
                valid = valid && !tooShort;
                field.classList.toggle('is-invalid', !valid);
                field.setAttribute('aria-invalid', String(!valid));
                if (!valid) {
                    const label = field.labels?.[0]?.textContent.trim() || field.name;
                    errors.push({field, message: label + ' : ' + (tooShort ? `Saisissez au moins ${minLength} caractères.` : field.validationMessage)});
                }
            }
            summary.replaceChildren();
            summary.hidden = errors.length === 0;
            if (errors.length) {
                const title = document.createElement('strong');
                title.textContent = 'Veuillez corriger les champs suivants :';
                const list = document.createElement('ul');
                errors.forEach(error => {
                    const item = document.createElement('li');
                    item.textContent = error.message;
                    list.append(item);
                });
                summary.append(title, list);
            }
            return errors;
        }
        form.addEventListener('submit', event => {
            if (submitting) { event.preventDefault(); return; }
            const errors = validate();
            if (errors.length) {
                event.preventDefault();
                resetButton();
                errors[0].field.focus();
                errors[0].field.scrollIntoView({behavior: 'smooth', block: 'center'});
                return;
            }
            submitting = true;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Enregistrement…';
        });
        form.addEventListener('input', () => { if (!summary.hidden) validate(); });
        form.addEventListener('change', () => { if (!summary.hidden) validate(); });
        window.addEventListener('pageshow', resetButton);
        resetButton();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeJobEdit);
    else initializeJobEdit();
    document.addEventListener('turbo:load', initializeJobEdit);
})();
