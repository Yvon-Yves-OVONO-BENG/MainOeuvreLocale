(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function wireQueueFilters() {
    const page = $('[data-moderation-page="queue"]');
    if (!page) return;

    const input = $('[data-mod-search="queue"]', page);
    const typeSelect = $('[data-mod-type="queue"]', page);
    const rows = $$('.js-mod-queue', page);

    const filter = () => {
      const q = (input?.value || '').trim().toLowerCase();
      const type = (typeSelect?.value || '').trim().toLowerCase();

      rows.forEach((row) => {
        const text = (row.dataset.modText || '').toLowerCase();
        const rowType = (row.dataset.modType || '').toLowerCase();

        const okText = !q || text.includes(q);
        const okType = !type || rowType === type;

        row.style.display = okText && okType ? '' : 'none';
      });
    };

    input?.addEventListener('input', filter);
    typeSelect?.addEventListener('change', filter);
    filter();
  }

  wireQueueFilters();
})();