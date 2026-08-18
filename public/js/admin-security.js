(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function wireSearch(pageSelector, inputSelector, itemSelector) {
    const page = $(pageSelector);
    if (!page) return;

    const input = $(inputSelector, page);
    const items = $$(itemSelector, page);

    const filter = () => {
      const q = (input?.value || "").trim().toLowerCase();

      items.forEach((item) => {
        const text = (item.dataset.secText || "").toLowerCase();
        item.style.display = !q || text.includes(q) ? "" : "none";
      });
    };

    input?.addEventListener("input", filter);
    filter();
  }

  function wireSearchAndAttributeFilter(pageSelector, inputSelector, itemSelector, attrSelector, attrName) {
    const page = $(pageSelector);
    if (!page) return;

    const input = $(inputSelector, page);
    const select = $(attrSelector, page);
    const items = $$(itemSelector, page);

    const filter = () => {
      const q = (input?.value || "").trim().toLowerCase();
      const attrValue = (select?.value || "").trim().toLowerCase();

      items.forEach((item) => {
        const text = (item.dataset.secText || "").toLowerCase();
        const itemAttr = (item.dataset[attrName] || "").toLowerCase();

        const okText = !q || text.includes(q);
        const okAttr = !attrValue || itemAttr === attrValue;

        item.style.display = okText && okAttr ? "" : "none";
      });
    };

    input?.addEventListener("input", filter);
    select?.addEventListener("change", filter);
    filter();
  }

  wireSearch('[data-security-page="roles"]', '[data-sec-search="roles"]', '.js-sec-role');
  wireSearch('[data-security-page="permissions"]', '[data-sec-search="permissions"]', '.js-sec-permission');
  wireSearchAndAttributeFilter('[data-security-page="sessions"]', '[data-sec-search="sessions"]', '.js-sec-session', '[data-sec-risk="sessions"]', 'secRisk');
  wireSearchAndAttributeFilter('[data-security-page="incidents"]', '[data-sec-search="incidents"]', '.js-sec-incident', '[data-sec-severity="incidents"]', 'secSeverity');
  wireSearchAndAttributeFilter('[data-security-page="2fa"]', '[data-sec-search="2fa"]', '.js-sec-2fa', '[data-sec-status="2fa"]', 'secStatus');
})();