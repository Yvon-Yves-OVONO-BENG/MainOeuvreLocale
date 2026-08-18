(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    return Promise.resolve();
  }

  // =========================
  // SUPPORT INDEX
  // =========================
  const supportPage = $('[data-admin-support-page="index"]');

  if (supportPage) {
    const searchInput = $('[data-support-search]', supportPage);
    const statusSelect = $('[data-support-status]', supportPage);
    const prioritySelect = $('[data-support-priority]', supportPage);
    const rows = $$('.js-ticket-row', supportPage);

    const filterTickets = () => {
      const q = (searchInput?.value || '').trim().toLowerCase();
      const status = statusSelect?.value || '';
      const priority = prioritySelect?.value || '';

      rows.forEach((row) => {
        const text = row.dataset.ticketText || '';
        const rowStatus = row.dataset.status || '';
        const rowPriority = row.dataset.priority || '';

        const okText = !q || text.includes(q);
        const okStatus = !status || rowStatus === status;
        const okPriority = !priority || rowPriority === priority;

        row.style.display = okText && okStatus && okPriority ? '' : 'none';
      });
    };

    searchInput?.addEventListener('input', filterTickets);
    statusSelect?.addEventListener('change', filterTickets);
    prioritySelect?.addEventListener('change', filterTickets);
    filterTickets();
  }

  // =========================
  // SUPPORT NEW
  // =========================
  const newPage = $('[data-admin-support-page="new"]');

  if (newPage) {
    const subject = $('[data-ticket-subject]', newPage);
    const body = $('[data-ticket-body]', newPage);
    const count = $('[data-ticket-count]', newPage);
    const previewSubject = $('[data-ticket-preview-subject]', newPage);
    const previewBody = $('[data-ticket-preview-body]', newPage);

    const updatePreview = () => {
      const subjectValue = (subject?.value || '').trim();
      const bodyValue = (body?.value || '').trim();

      if (previewSubject) {
        previewSubject.textContent = subjectValue || 'Sans titre';
      }

      if (previewBody) {
        previewBody.textContent = bodyValue || 'Le détail du ticket apparaîtra ici pendant la saisie.';
      }

      if (count) {
        count.textContent = String((body?.value || '').length);
      }
    };

    subject?.addEventListener('input', updatePreview);
    body?.addEventListener('input', updatePreview);
    updatePreview();
  }

  // =========================
  // FAQ
  // =========================
  const faqPage = $('[data-admin-support-page="faq"]');

  if (faqPage) {
    const searchInput = $('[data-faq-search]', faqPage);
    const chips = $$('[data-faq-chip]', faqPage);
    const cards = $$('.js-faq-card', faqPage);

    let activeCategory = '';

    const filterFaqs = () => {
      const q = (searchInput?.value || '').trim().toLowerCase();

      cards.forEach((card) => {
        const text = card.dataset.faqText || '';
        const category = card.dataset.faqCategory || '';
        const okText = !q || text.includes(q);
        const okCategory = !activeCategory || category === activeCategory;

        card.style.display = okText && okCategory ? '' : 'none';
      });
    };

    chips.forEach((chip) => {
      chip.addEventListener('click', () => {
        chips.forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        activeCategory = chip.dataset.faqChip || '';
        filterFaqs();
      });
    });

    searchInput?.addEventListener('input', filterFaqs);
    filterFaqs();
  }

  // =========================
  // TEMPLATES
  // =========================
  const templatesPage = $('[data-admin-support-page="templates"]');

  if (templatesPage) {
    const searchInput = $('[data-template-search]', templatesPage);
    const chips = $$('[data-template-chip]', templatesPage);
    const cards = $$('.js-template-card', templatesPage);

    const previewTitle = $('[data-template-preview-title]', templatesPage);
    const previewSubject = $('[data-template-preview-subject]', templatesPage);
    const previewBody = $('[data-template-preview-body]', templatesPage);
    const copyBtn = $('[data-template-copy]', templatesPage);

    let activeCategory = '';
    let activeCard = null;

    const renderTemplate = (card) => {
      if (!card) {
        if (previewTitle) previewTitle.textContent = 'Aucun template sélectionné';
        if (previewSubject) previewSubject.textContent = '—';
        if (previewBody) previewBody.textContent = 'Choisis un template dans la liste pour l’afficher ici.';
        activeCard = null;
        return;
      }

      activeCard = card;

      if (previewTitle) previewTitle.textContent = card.dataset.templateTitle || '';
      if (previewSubject) previewSubject.textContent = card.dataset.templateSubject || '';
      if (previewBody) previewBody.textContent = card.dataset.templateBody || '';

      cards.forEach((c) => c.style.outline = '');
      card.style.outline = '2px solid rgba(79,70,229,.22)';
    };

    const filterTemplates = () => {
      const q = (searchInput?.value || '').trim().toLowerCase();

      let firstVisible = null;

      cards.forEach((card) => {
        const text = card.dataset.templateText || '';
        const category = card.dataset.templateCategory || '';
        const okText = !q || text.includes(q);
        const okCategory = !activeCategory || category === activeCategory;
        const visible = okText && okCategory;

        card.style.display = visible ? '' : 'none';

        if (visible && !firstVisible) {
          firstVisible = card;
        }
      });

      if (!activeCard || activeCard.style.display === 'none') {
        renderTemplate(firstVisible);
      }
    };

    chips.forEach((chip) => {
      chip.addEventListener('click', () => {
        chips.forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        activeCategory = chip.dataset.templateChip || '';
        filterTemplates();
      });
    });

    cards.forEach((card) => {
      card.addEventListener('click', () => renderTemplate(card));
    });

    searchInput?.addEventListener('input', filterTemplates);

    copyBtn?.addEventListener('click', async () => {
      const title = previewTitle?.textContent || '';
      const subject = previewSubject?.textContent || '';
      const body = previewBody?.textContent || '';
      const text = `${title}\n\nObjet: ${subject}\n\n${body}`;

      await copyText(text);

      const oldText = copyBtn.textContent;
      copyBtn.textContent = 'Copié ✓';
      setTimeout(() => {
        copyBtn.textContent = oldText;
      }, 1400);
    });

    filterTemplates();
  }
})();