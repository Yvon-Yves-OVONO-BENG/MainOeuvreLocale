(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function wireSearch(pageSelector, inputSelector, rowSelector) {
    const page = $(pageSelector);
    if (!page) return;

    const input = $(inputSelector, page);
    const rows = $$(rowSelector, page);

    const filter = () => {
      const q = (input?.value || '').trim().toLowerCase();

      rows.forEach((row) => {
        const text = (row.dataset.centerText || '').toLowerCase();
        row.style.display = !q || text.includes(q) ? '' : 'none';
      });
    };

    input?.addEventListener('input', filter);
    filter();
  }

  wireSearch('[data-center-page="backups"]', '[data-center-search="backups"]', '.js-center-backup');
  wireSearch('[data-center-page="integrations"]', '[data-center-search="integrations"]', '.js-center-integration');
})();