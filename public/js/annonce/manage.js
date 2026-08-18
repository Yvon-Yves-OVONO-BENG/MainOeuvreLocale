(function () {
    const manager = document.getElementById('annonceManager');
    if (!manager || manager.dataset.ready === '1') return;
    manager.dataset.ready = '1';

    const tbody = document.getElementById('annonceRows');
    const search = document.getElementById('annonceSearch');
    const limitSelect = document.getElementById('annonceLimit');
    const selectAll = document.getElementById('annonceSelectAll');
    const bulkButton = document.getElementById('annonceBulkDelete');
    const empty = document.getElementById('annonceEmpty');
    const info = document.getElementById('annoncePageInfo');
    const pageNumber = document.getElementById('annoncePageNumber');
    const prev = document.getElementById('annoncePrev');
    const next = document.getElementById('annonceNext');
    let page = 1;

    const rows = () => Array.from(tbody.querySelectorAll('.js-annonce-row'));
    const normalized = value => (value || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const filteredRows = () => {
        const query = normalized(search.value.trim());
        return rows().filter(row => !query || normalized(row.textContent).includes(query));
    };
    const selected = () => rows().filter(row => row.querySelector('.js-row-check').checked);

    function updateBulk() {
        const count = selected().length;
        bulkButton.classList.toggle('show', count > 0);
        bulkButton.querySelector('span').textContent = `Supprimer ${count} annonce${count > 1 ? 's' : ''}`;
    }

    function render() {
        const filtered = filteredRows();
        const limit = parseInt(limitSelect.value, 10);
        const pages = Math.max(1, Math.ceil(filtered.length / limit));
        page = Math.min(Math.max(1, page), pages);
        const start = (page - 1) * limit;
        const visible = new Set(filtered.slice(start, start + limit));
        rows().forEach(row => row.hidden = !visible.has(row));
        empty.hidden = filtered.length > 0;
        info.textContent = filtered.length ? `${start + 1} à ${Math.min(start + limit, filtered.length)} sur ${filtered.length} annonce${filtered.length > 1 ? 's' : ''}` : '0 annonce';
        pageNumber.textContent = `${page} / ${pages}`;
        prev.disabled = page <= 1;
        next.disabled = page >= pages;
        selectAll.checked = visible.size > 0 && Array.from(visible).every(row => row.querySelector('.js-row-check').checked);
        selectAll.indeterminate = !selectAll.checked && Array.from(visible).some(row => row.querySelector('.js-row-check').checked);
        updateBulk();
    }

    async function post(url, params) {
        const response = await fetch(url, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body: params.toString()});
        let data = {};
        try { data = await response.json(); } catch (e) {}
        if (!response.ok || !data.success) throw new Error(data.message || 'Une erreur est survenue.');
        return data;
    }

    function alertBox(options) {
        if (window.Swal) return Swal.fire(options);
        return Promise.resolve({isConfirmed: options.showCancelButton ? window.confirm(options.text || 'Confirmer ?') : true});
    }

    search.addEventListener('input', () => { page = 1; render(); });
    limitSelect.addEventListener('change', () => { page = 1; render(); });
    prev.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    selectAll.addEventListener('change', () => { rows().filter(row => !row.hidden).forEach(row => row.querySelector('.js-row-check').checked = selectAll.checked); render(); });
    tbody.addEventListener('change', event => { if (event.target.classList.contains('js-row-check')) render(); });

    tbody.addEventListener('change', async event => {
        const toggle = event.target.closest('.js-display-toggle');
        if (!toggle) return;
        const previous = !toggle.checked;
        toggle.disabled = true;
        try {
            const data = await post(toggle.dataset.url, new URLSearchParams({_token: toggle.dataset.token, enabled: toggle.checked ? '1' : '0'}));
            const row = toggle.closest('.js-annonce-row');
            const badge = row.querySelector('.js-status');
            badge.className = `anm-status ${data.status} js-status`;
            badge.textContent = data.status === 'pending' ? 'En modération' : 'Brouillon';
            await alertBox({icon: 'success', title: toggle.checked ? 'Annonce soumise' : 'Affichage désactivé', text: data.message, confirmButtonColor: '#155eef'});
        } catch (error) {
            toggle.checked = previous;
            await alertBox({icon: 'error', title: 'Action impossible', text: error.message, confirmButtonColor: '#d3344c'});
        } finally { toggle.disabled = false; }
    });

    tbody.addEventListener('click', async event => {
        const button = event.target.closest('.js-delete-annonce');
        if (!button) return;
        const answer = await alertBox({icon: 'warning', title: 'Supprimer cette annonce ?', text: 'Cette action est définitive.', showCancelButton: true, confirmButtonText: 'Oui, supprimer', cancelButtonText: 'Annuler', confirmButtonColor: '#d3344c'});
        if (!answer.isConfirmed) return;
        try {
            const data = await post(button.dataset.url, new URLSearchParams({_token: button.dataset.token}));
            button.closest('.js-annonce-row').remove();
            render();
            await alertBox({icon: 'success', title: 'Annonce supprimée', text: data.message, confirmButtonColor: '#155eef'});
        } catch (error) { await alertBox({icon: 'error', title: 'Suppression impossible', text: error.message}); }
    });

    bulkButton.addEventListener('click', async () => {
        const picked = selected();
        if (!picked.length) return;
        const answer = await alertBox({icon: 'warning', title: `Supprimer ${picked.length} annonce${picked.length > 1 ? 's' : ''} ?`, text: 'Cette action est définitive.', showCancelButton: true, confirmButtonText: 'Supprimer la sélection', cancelButtonText: 'Annuler', confirmButtonColor: '#d3344c'});
        if (!answer.isConfirmed) return;
        const params = new URLSearchParams({_token: manager.dataset.bulkToken});
        picked.forEach(row => params.append('ids[]', row.dataset.id));
        try {
            const data = await post(manager.dataset.bulkUrl, params);
            const deleted = new Set((data.deletedIds || []).map(String));
            rows().filter(row => deleted.has(row.dataset.id)).forEach(row => row.remove());
            render();
            await alertBox({icon: 'success', title: 'Suppression terminée', text: data.message, confirmButtonColor: '#155eef'});
        } catch (error) { await alertBox({icon: 'error', title: 'Suppression impossible', text: error.message}); }
    });

    render();
})();
