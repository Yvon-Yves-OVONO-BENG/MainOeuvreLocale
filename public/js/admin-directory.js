(() => {
  const forms = document.querySelectorAll('[data-directory-search-form]');

  forms.forEach((form) => {
    const input = form.querySelector('input[type="search"]');
    if (!input) return;

    let timer = null;

    input.addEventListener('input', () => {
      clearTimeout(timer);

      timer = setTimeout(() => {
        const url = new URL(window.location.href);

        if (input.value.trim() === '') {
          url.searchParams.delete('q');
        } else {
          url.searchParams.set('q', input.value.trim());
        }

        url.searchParams.set('page', '1');
        window.location.href = url.toString();
      }, 350);
    });
  });
})();