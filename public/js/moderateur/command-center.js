(function () {
  const root = document.getElementById('modCommandCenter');
  if (!root) return;

  // ✅ Bootstrap offcanvas open
  window.MOLChatOpen = function () {
    if (!window.bootstrap || !bootstrap.Offcanvas) return;
    const el = document.getElementById('molChatOffcanvas');
    if (!el) return;
    bootstrap.Offcanvas.getOrCreateInstance(el).show();
  };

  // ✅ Toast minimal (sans lib)
  function toast(msg, type = 'info') {
    const wrap = document.createElement('div');
    wrap.style.position = 'fixed';
    wrap.style.right = '18px';
    wrap.style.bottom = '18px';
    wrap.style.zIndex = '999999';
    wrap.innerHTML = `
      <div style="
        min-width: 260px;
        max-width: 360px;
        padding: 12px 14px;
        border-radius: 16px;
        border:1px solid rgba(15,23,42,.12);
        background: rgba(255,255,255,.96);
        box-shadow: 0 22px 70px rgba(15,23,42,.14);
        font-weight: 800;
        color: rgba(15,23,42,.92);
      ">
        ${type === 'error' ? '🛑' : type === 'success' ? '✅' : 'ℹ️'} ${msg}
      </div>
    `;
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 2400);
  }
  window.MOLToast = toast;

  // ✅ Suggest global search
  const suggestUrl = root.dataset.suggestUrl;
  const input = document.getElementById('modGlobalSearch');
  const list = document.getElementById('modSuggestList');
  if (!suggestUrl || !input || !list) return;

  let t = null;
  let last = '';

  function hide() {
    list.style.display = 'none';
    list.innerHTML = '';
  }

  function render(items) {
    if (!items || items.length === 0) return hide();

    list.innerHTML = items.map(it => `
      <a class="mod-suggest-item" href="${it.url}">
        <span>${it.label}</span>
        <small>${it.type}</small>
      </a>
    `).join('');
    list.style.display = 'block';
  }

  async function fetchSuggest(q) {
    const url = new URL(suggestUrl, window.location.origin);
    url.searchParams.set('q', q);
    const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) return [];
    const data = await res.json();
    return data.items || [];
  }

  input.addEventListener('input', () => {
    const q = (input.value || '').trim();
    if (q.length < 2) return hide();
    if (q === last) return;

    clearTimeout(t);
    t = setTimeout(async () => {
      last = q;
      try {
        const items = await fetchSuggest(q);
        render(items);
      } catch (e) {
        // silence
      }
    }, 180);
  });

  document.addEventListener('click', (e) => {
    if (!list.contains(e.target) && e.target !== input) hide();
  });

  input.addEventListener('focus', () => {
    if ((input.value || '').trim().length >= 2 && list.innerHTML.trim().length) {
      list.style.display = 'block';
    }
  });
})();