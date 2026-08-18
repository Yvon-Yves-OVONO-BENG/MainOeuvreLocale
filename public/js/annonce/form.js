(() => {
    'use strict';

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    };

    const initProfessionLoader = () => {
        const category = document.querySelector('.js-annonce-categorie');
        const profession = document.querySelector('.js-annonce-profession');

        if (
            !(category instanceof HTMLSelectElement)
            || !(profession instanceof HTMLSelectElement)
            || category.dataset.professionLoaderBound === '1'
        ) {
            return;
        }

        category.dataset.professionLoaderBound = '1';

        category.addEventListener('change', async () => {
            const categoryId = category.value;

            profession.disabled = true;
            profession.innerHTML = '<option value="">Chargement…</option>';

            if (!categoryId) {
                profession.innerHTML = '<option value="">Choisissez d’abord une catégorie</option>';
                profession.disabled = true;
                return;
            }

            try {
                const pattern = category.dataset.professionsUrl;

                if (!pattern) {
                    throw new Error('URL des professions introuvable.');
                }

                const url = pattern.replace(
                    /\/0\/professions(?=\?|$)/,
                    `/${encodeURIComponent(categoryId)}/professions`
                );

                const currentPath = window.location.pathname;
                const publicMarker = '/public/';
                const publicPosition = currentPath.indexOf(publicMarker);

                const resolvedUrl = publicPosition !== -1 && url.startsWith('/api/')
                    ? currentPath.slice(0, publicPosition + '/public'.length) + url
                    : url;

                const response = await fetch(resolvedUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Erreur de chargement des professions.');
                }

                const rows = await response.json();

                profession.innerHTML = '<option value="">Sélectionnez une profession</option>';

                if (Array.isArray(rows)) {
                    rows.forEach((row) => {
                        const option = document.createElement('option');
                        option.value = String(row.id ?? '');
                        option.textContent = String(row.label ?? '');
                        profession.appendChild(option);
                    });
                }

                profession.disabled = false;
            } catch (error) {
                profession.innerHTML = '<option value="">Impossible de charger les professions</option>';
                profession.disabled = false;
                console.error(error);
            }
        });
    };

    const initCreationSuccessModal = () => {
        const payload = document.querySelector('[data-annonce-creation-success]');

        if (!(payload instanceof HTMLElement) || payload.dataset.modalShown === '1') {
            return;
        }

        payload.dataset.modalShown = '1';

        if (!window.Swal) {
            return;
        }

        const requested = payload.dataset.requested === '1';
        const title = payload.dataset.annonceTitle || '';
        const manageUrl = payload.dataset.manageUrl || '';

        window.Swal.fire({
            icon: 'success',
            title: requested
                ? 'Annonce enregistrée et soumise !'
                : 'Annonce enregistrée !',
            html: requested
                ? `<div class="an-success-message">Votre annonce <strong>${escapeHtml(title)}</strong> a bien été enregistrée. Comme vous avez activé <strong>Afficher maintenant</strong>, elle est soumise à l’appréciation d’un modérateur avant publication.</div>`
                : `<div class="an-success-message">Votre annonce <strong>${escapeHtml(title)}</strong> a bien été enregistrée. Lorsque vous déciderez de l’afficher, elle sera soumise à l’appréciation d’un modérateur avant affichage.</div>`,
            confirmButtonText: 'Voir mes annonces',
            confirmButtonColor: '#155eef',
            showCancelButton: true,
            cancelButtonText: 'Créer une autre annonce',
            customClass: {
                popup: 'rounded-4',
            },
            allowOutsideClick: false,
        }).then((result) => {
            if (result.isConfirmed && manageUrl) {
                window.location.href = manageUrl;
            }
        });
    };

    const initialize = () => {
        initProfessionLoader();
        initCreationSuccessModal();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }

    document.addEventListener('turbo:load', initialize);
})();
