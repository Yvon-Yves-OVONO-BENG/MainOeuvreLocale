(() => {
  'use strict';

  const SELECTOR = '[data-smart-keyword], [data-smart-location]';
  const PORTAL_Z_INDEX = '2147483000';

  const normalize = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

  const isDigitsOnly = (value) => /^\d+$/.test(String(value || '').replace(/[\s.,'’()\-+/]/g, ''));

  const validate = (input) => {
    const text = input.value.trim();
    if (!text) return { valid: true, message: '' };

    const isLocation = input.matches('[data-smart-location]');
    const numericMessage = input.dataset.numericError || (
      isLocation
        ? 'Saisissez une ville, une région ou un pays, pas uniquement des chiffres.'
        : 'Saisissez un métier, un poste ou une compétence, pas uniquement des chiffres.'
    );
    const characterMessage = input.dataset.characterError || (
      isLocation
        ? 'Utilisez des lettres, espaces, apostrophes, virgules, points, tirets ou barres obliques.'
        : 'Utilisez des lettres, espaces, apostrophes, +, - ou /.'
    );

    if (isDigitsOnly(text)) {
      return { valid: false, message: numericMessage };
    }

    const pattern = isLocation
      ? /^[\p{L}\p{M}0-9 .,'’+\-/()]+$/u
      : /^[\p{L}\p{M}0-9 .,'’+\-/()]+$/u;

    if (!pattern.test(text)) {
      return { valid: false, message: characterMessage };
    }

    return { valid: true, message: '' };
  };

  const createPortalElement = (tagName, className) => {
    const element = document.createElement(tagName);
    element.className = className;
    element.style.zIndex = PORTAL_Z_INDEX;
    document.body.append(element);
    return element;
  };

  const init = (input) => {
    if (input.dataset.smartSearchReady === '1') return;
    input.dataset.smartSearchReady = '1';

    const endpoint = input.dataset.suggestUrl;
    const type = input.dataset.suggestType || (input.matches('[data-smart-location]') ? 'location' : 'keyword');
    const container = input.closest('[data-smart-search-container]') || input.parentElement;
    if (container) container.classList.add('mol-smart-search');

    const list = createPortalElement('ul', 'mol-smart-search__list');
    list.setAttribute('role', 'listbox');
    list.id = `${input.id || `mol-search-${Math.random().toString(36).slice(2)}`}-suggestions`;

    const error = createPortalElement('div', 'mol-smart-search__error');
    error.setAttribute('role', 'alert');
    error.id = `${list.id}-error`;

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-describedby', error.id);
    input.setAttribute('aria-expanded', 'false');

    let timer = null;
    let controller = null;
    let items = [];
    let activeIndex = -1;
    let openedElement = null;
    let suppressNextInput = false;

    const syncPortalPosition = (element) => {
      if (!element || !element.classList.contains('is-open')) return;

      const rect = input.getBoundingClientRect();
      const viewportWidth = document.documentElement.clientWidth;
      const viewportHeight = document.documentElement.clientHeight;
      const margin = 8;
      const width = Math.max(180, Math.min(rect.width, viewportWidth - (margin * 2)));
      const left = Math.max(margin, Math.min(rect.left, viewportWidth - width - margin));

      element.style.position = 'fixed';
      element.style.left = `${Math.round(left)}px`;
      element.style.width = `${Math.round(width)}px`;
      element.style.right = 'auto';
      element.style.bottom = 'auto';
      element.classList.remove('is-above');

      const preferredTop = rect.bottom + 7;
      const elementHeight = Math.min(element.scrollHeight || 80, type === 'location' ? 300 : 280);
      const roomBelow = viewportHeight - preferredTop - margin;
      const roomAbove = rect.top - margin;

      if (roomBelow < Math.min(elementHeight, 170) && roomAbove > roomBelow) {
        element.style.top = `${Math.max(margin, Math.round(rect.top - elementHeight - 7))}px`;
        element.classList.add('is-above');
      } else {
        element.style.top = `${Math.round(preferredTop)}px`;
      }
    };

    const syncOpenPortal = () => {
      if (openedElement) syncPortalPosition(openedElement);
    };

    const closeList = () => {
      list.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
      if (openedElement === list) openedElement = null;
    };

    const hideError = () => {
      error.classList.remove('is-open');
      if (openedElement === error) openedElement = null;
    };

    const showError = (message = '', shouldShake = false) => {
      const hasMessage = Boolean(message);
      input.classList.toggle('is-invalid', hasMessage);
      if (container) {
        container.classList.toggle('mol-smart-search--invalid', hasMessage);
      }
      input.setAttribute('aria-invalid', hasMessage ? 'true' : 'false');
      input.setCustomValidity(message);
      error.textContent = message;

      if (hasMessage) {
        closeList();
        error.classList.add('is-open');
        openedElement = error;
        requestAnimationFrame(() => syncPortalPosition(error));

        if (shouldShake && container) {
          container.classList.remove('mol-smart-search--shake');
          void container.offsetWidth;
          container.classList.add('mol-smart-search--shake');
        }
      } else {
        hideError();
      }
    };

    const choose = (item) => {
      input.value = item.label;
      suppressNextInput = true;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      showError('');
      closeList();
      input.focus();
    };

    const render = (results) => {
      items = results;
      list.replaceChildren();

      results.forEach((item, index) => {
        const li = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mol-smart-search__item';
        button.setAttribute('role', 'option');
        button.dataset.index = String(index);

        const label = document.createElement('span');
        label.className = 'mol-smart-search__label';
        label.textContent = item.label;

        const itemType = document.createElement('span');
        itemType.className = 'mol-smart-search__type';
        itemType.textContent = item.type || '';

        button.append(label, itemType);
        button.addEventListener('mousedown', (event) => {
          event.preventDefault();
          choose(item);
        });
        li.append(button);
        list.append(li);
      });

      if (results.length > 0) {
        hideError();
        list.classList.add('is-open');
        openedElement = list;
        input.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => syncPortalPosition(list));
      } else {
        closeList();
      }
    };

    const requestSuggestions = async () => {
      const validation = validate(input);
      showError(validation.valid ? '' : validation.message);
      if (!validation.valid || !endpoint || normalize(input.value).length < 2) {
        render([]);
        return;
      }

      controller?.abort();
      controller = new AbortController();

      try {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('q', input.value.trim());
        url.searchParams.set('type', type);
        url.searchParams.set('limit', '10');

        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
          credentials: 'same-origin',
        });
        const raw = await response.text();
        let data;

        try {
          data = JSON.parse(raw);
        } catch (_) {
          render([]);
          return;
        }

        if (!response.ok || data.ok === false) {
          showError(data.message || 'Recherche invalide.');
          render([]);
          return;
        }

        render(Array.isArray(data.items) ? data.items : []);
      } catch (caughtError) {
        if (caughtError.name !== 'AbortError') render([]);
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);

      if (suppressNextInput) {
        suppressNextInput = false;
        showError('');
        render([]);
        return;
      }

      const validation = validate(input);
      showError(validation.valid ? '' : validation.message);

      if (!validation.valid) {
        controller?.abort();
        render([]);
        return;
      }

      timer = window.setTimeout(requestSuggestions, 180);
    });

    input.addEventListener('blur', () => {
      window.setTimeout(() => {
        closeList();
        if (!input.value.trim()) showError('');
      }, 140);
    });

    input.addEventListener('focus', () => {
      const validation = validate(input);
      if (!validation.valid) {
        showError(validation.message);
        return;
      }
      if (items.length > 0) {
        list.classList.add('is-open');
        openedElement = list;
        input.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => syncPortalPosition(list));
      }
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeList();
        hideError();
        return;
      }

      if (!list.classList.contains('is-open') || items.length === 0) return;
      if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
      event.preventDefault();

      if (event.key === 'Enter' && activeIndex >= 0) {
        choose(items[activeIndex]);
        return;
      }

      activeIndex += event.key === 'ArrowDown' ? 1 : -1;
      activeIndex = (activeIndex + items.length) % items.length;
      list.querySelectorAll('.mol-smart-search__item').forEach((button, index) => {
        button.classList.toggle('is-active', index === activeIndex);
        button.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
      });
    });

    input.form?.addEventListener('submit', (event) => {
      const validation = validate(input);
      showError(validation.valid ? '' : validation.message, !validation.valid);
      if (!validation.valid) {
        event.preventDefault();
        event.stopImmediatePropagation();
        input.focus({ preventScroll: true });
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });

    window.addEventListener('resize', syncOpenPortal, { passive: true });
    window.addEventListener('scroll', syncOpenPortal, { passive: true, capture: true });
  };

  const boot = () => document.querySelectorAll(SELECTOR).forEach(init);
  document.addEventListener('DOMContentLoaded', boot, { once: true });
  document.addEventListener('turbo:load', boot);
})();
