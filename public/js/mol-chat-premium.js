/* public/js/mol-chat-premium.js
   Chat Messenger Premium SaaS++ — prêt Symfony + Mercure + fallback polling
*/


(() => {
    
    if (window.__MOL_CHAT_MESSENGER_PREMIUM_BOOTED__) return;
    window.__MOL_CHAT_MESSENGER_PREMIUM_BOOTED__ = true;

    const cfg = window.MOL_CHAT || {};
    const urls = cfg.urls || {};
    const groupUrls = cfg.groups || {};
    const presenceUrls = cfg.presence || {};
    const callUrls = cfg.call || {};
    const assets = cfg.assets || {};

    const state = {
        panelOpen: false,
        currentTab: 'private',
        privateItems: [],
        groupItems: [],
        threads: new Map(),
        eventSources: new Map(),
        recorders: new Map(),
        typingTimers: new Map(),
        typingVisible: new Set(),
        listPoll: null,
        unreadPoll: null,
        z: 1,
    };

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const panel = $('#molChatPanel');
    const fab = $('#molChatFab');
    const privateList = $('#molChatPrivateList');
    const groupList = $('#molChatGroupList');
    const dock = $('#molChatThreadDock');
    const toastHost = $('#molChatToasts');

    if (!panel || !fab || !privateList || !dock) return;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function toInt(value, fallback = 0) {
        const n = Number(value);
        return Number.isFinite(n) ? n : fallback;
    }

    function tpl(template, id) {
        const s = String(id);
        return String(template || '')
            .replace('__ID__', encodeURIComponent(s))
            .replace('__CONV__', encodeURIComponent(s))
            .replace('__CALL__', encodeURIComponent(s));
    }

    function buildUrl(path) {
        if (!path) return '';

        const raw = String(path).trim();
        const origin = window.location.origin;

        let base = String(cfg.baseUrl || window.MOL_CHAT?.baseUrl || '').trim();

        if (base === '/' || base === '.') {
            base = '';
        }

        base = base.replace(/\/+$/, '');

        /*
        * Cas URL absolue :
        * http://localhost/uploads/... doit devenir :
        * http://localhost/maindoeuvrelocale/public/uploads/...
        */
        if (/^https?:\/\//i.test(raw)) {
            try {
                const parsed = new URL(raw);

                const isSameOrigin = parsed.origin === origin;
                const needsBase =
                    base &&
                    isSameOrigin &&
                    !parsed.pathname.startsWith(base + '/') &&
                    (
                        parsed.pathname.startsWith('/uploads/') ||
                        parsed.pathname.startsWith('/media/') ||
                        parsed.pathname.startsWith('/files/')
                    );

                if (needsBase) {
                    return origin + base + parsed.pathname + parsed.search + parsed.hash;
                }

                return raw;
            } catch (_) {
                return raw;
            }
        }

        let cleanPath = raw;

        if (base && cleanPath.startsWith(base + '/')) {
            return origin + cleanPath;
        }

        if (cleanPath.startsWith('/')) {
            return origin + base + cleanPath;
        }

        return origin + base + '/' + cleanPath.replace(/^\/+/, '');
    }

    async function fetchJson(url, options = {}) {
        const finalOptions = {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        };

        const response = await fetch(buildUrl(url), finalOptions);
        const text = await response.text();
        let data = {};

        try {
            data = text ? JSON.parse(text) : {};
        } catch (_) {
            data = { raw: text };
        }

        if (!response.ok || data.ok === false || data.success === false) {
            const message = data.error || data.message || `HTTP ${response.status}`;
            throw new Error(message);
        }

        return data;
    }

    function debounce(fn, delay = 260) {
        let timer = null;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    function formatTime(value) {
        if (!value) return '';
        const raw = String(value);
        const d = new Date(raw.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) {
            const m = raw.match(/(\d{2}:\d{2})/);
            return m ? m[1] : raw;
        }

        const now = new Date();
        const diff = Math.max(0, now - d);
        const min = Math.floor(diff / 60000);
        const hrs = Math.floor(min / 60);
        const days = Math.floor(hrs / 24);

        if (min < 1) return 'Just now';
        if (min < 60) return `${min}min`;
        if (hrs < 24) return `${hrs}hrs`;
        if (days < 7) return `${days} day${days > 1 ? 's' : ''}`;
        return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
    }

    function bytesToSize(bytes) {
        const n = Number(bytes || 0);
        if (!n) return '';
        const units = ['o', 'Ko', 'Mo', 'Go'];
        let i = 0;
        let v = n;
        while (v >= 1024 && i < units.length - 1) {
            v /= 1024;
            i++;
        }
        return `${v.toFixed(v >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function defaultAvatar() {
        return assets.defaultAvatar || '/uploads/profiles/avatar.png';
    }

    function normalizeAvatar(value) {
        let v = String(value || '').trim();

        if (!v || v === 'null' || v === 'undefined' || v.endsWith('/null') || v.endsWith('/undefined')) {
            return buildUrl(defaultAvatar());
        }

        if (/^https?:\/\//i.test(v) || v.startsWith('data:image/') || v.startsWith('blob:')) {
            return v;
        }

        v = v.replaceAll('\\', '/');

        if (v.startsWith('/')) {
            return buildUrl(v);
        }

        if (v.startsWith('uploads/')) {
            return buildUrl('/' + v);
        }

        if (v.includes('/')) {
            return buildUrl('/' + v.replace(/^\/+/, ''));
        }

        return buildUrl(
            (assets.profilesBase || '/uploads/profiles/').replace(/\/?$/, '/')
            + encodeURIComponent(v)
        );
    }

    function initials(name) {
        return String(name || 'G')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'G';
    }

    function presenceMeta(item = {}) {
        const p = String(item.presence || (item.online ? 'online' : 'offline') || 'offline').toLowerCase();
        if (p === 'online') {
            return { dot: '', label: 'Online', statusClass: '', online: true };
        }
        if (p === 'recently_offline') {
            return { dot: 'is-recently-offline', label: 'Recently offline', statusClass: 'is-offline', online: false };
        }
        return { dot: 'is-offline', label: 'Offline', statusClass: 'is-offline', online: false };
    }

    function notify(message, type = 'info') {
        if (!toastHost) return;
        const toast = document.createElement('div');
        toast.className = `mol-chat-toast ${type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : ''}`;
        const icon = type === 'error' ? 'bi-exclamation-triangle-fill' : type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill';
        toast.innerHTML = `<i class="bi ${icon}"></i><div>${escapeHtml(message)}</div>`;
        toastHost.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 180);
        }, 2800);
    }

    function askText({ title = 'Information', description = '', placeholder = '', multiline = false, confirm = 'OK' } = {}) {
        return new Promise(resolve => {
            const backdrop = document.createElement('div');
            backdrop.className = 'mol-chat-modal-backdrop is-open';
            backdrop.innerHTML = `
                <div class="mol-chat-modal" role="dialog" aria-modal="true">
                    <h4>${escapeHtml(title)}</h4>
                    ${description ? `<p>${escapeHtml(description)}</p>` : ''}
                    ${multiline
                        ? `<textarea rows="4" data-modal-input placeholder="${escapeHtml(placeholder)}"></textarea>`
                        : `<input type="text" data-modal-input placeholder="${escapeHtml(placeholder)}">`}
                    <div class="mol-chat-modal-actions">
                        <button type="button" class="mol-chat-modal-cancel" data-modal-cancel>Annuler</button>
                        <button type="button" class="mol-chat-modal-confirm" data-modal-confirm>${escapeHtml(confirm)}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(backdrop);
            const input = $('[data-modal-input]', backdrop);
            const close = value => {
                backdrop.remove();
                resolve(value);
            };
            $('[data-modal-cancel]', backdrop).addEventListener('click', () => close(null));
            $('[data-modal-confirm]', backdrop).addEventListener('click', () => close(input.value || ''));
            backdrop.addEventListener('click', e => {
                if (e.target === backdrop) close(null);
            });
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter' && !multiline) close(input.value || '');
                if (e.key === 'Escape') close(null);
            });
            setTimeout(() => input.focus(), 60);
        });
    }

    function askConfirm(title, description = '') {
        return new Promise(resolve => {
            const backdrop = document.createElement('div');
            backdrop.className = 'mol-chat-modal-backdrop is-open';
            backdrop.innerHTML = `
                <div class="mol-chat-modal" role="dialog" aria-modal="true">
                    <h4>${escapeHtml(title)}</h4>
                    ${description ? `<p>${escapeHtml(description)}</p>` : ''}
                    <div class="mol-chat-modal-actions">
                        <button type="button" class="mol-chat-modal-cancel" data-modal-cancel>Annuler</button>
                        <button type="button" class="mol-chat-modal-confirm" data-modal-confirm>Continuer</button>
                    </div>
                </div>
            `;
            document.body.appendChild(backdrop);
            const close = value => { backdrop.remove(); resolve(value); };
            $('[data-modal-cancel]', backdrop).addEventListener('click', () => close(false));
            $('[data-modal-confirm]', backdrop).addEventListener('click', () => close(true));
            backdrop.addEventListener('click', e => { if (e.target === backdrop) close(false); });
        });
    }

    function fixChatPanelPosition() {
        const navbar = document.querySelector('.pm-navbar');
        const panel = document.getElementById('molChatPanel');

        const navbarHeight = navbar ? Math.ceil(navbar.offsetHeight || 74) : 74;
        const panelTop = navbarHeight + 12;

        document.documentElement.style.setProperty('--mol-navbar-height', navbarHeight + 'px');
        document.documentElement.style.setProperty('--mol-chat-panel-top', panelTop + 'px');

        if (panel) {
            panel.style.position = 'fixed';
            panel.style.top = panelTop + 'px';
            panel.style.bottom = 'auto';
            panel.style.maxHeight = `calc(100vh - ${panelTop + 16}px)`;
            panel.style.zIndex = '2500';
        }
    }

    function openPanel() {
        state.panelOpen = true;

        fixChatPanelPosition();

        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');

        loadPrivateConversations();
        loadGroups();
        updateUnreadBadge();
    }

    function closePanel() {
        state.panelOpen = false;
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        closeAllMenus();
    }

    async function loadPrivateConversations() {
        privateList.innerHTML = '<div class="mol-chat-loading">Chargement...</div>';
        try {
            const input = $('#molChatSearchInput');
            const q = input?.value?.trim() || '';
            const url = new URL(buildUrl(urls.conversations));
            url.searchParams.set('limit', '80');
            if (q) url.searchParams.set('q', q);
            url.searchParams.set('_ts', Date.now().toString());
            const data = await fetchJson(url.toString());
            state.privateItems = data.items || data.conversations || [];
            renderPrivateConversations(state.privateItems);
        } catch (error) {
            privateList.innerHTML = `<div class="mol-chat-empty">${escapeHtml(error.message || 'Erreur de chargement.')}</div>`;
        }
    }

    async function loadGroups() {
        if (!groupList || !groupUrls.mine) return;
        groupList.innerHTML = '<div class="mol-chat-loading">Chargement...</div>';
        try {
            const data = await fetchJson(groupUrls.mine + (String(groupUrls.mine).includes('?') ? '&' : '?') + '_ts=' + Date.now());
            state.groupItems = data.items || [];
            renderGroups(state.groupItems);
        } catch (error) {
            groupList.innerHTML = `<div class="mol-chat-empty">${escapeHtml(error.message || 'Groupes indisponibles.')}</div>`;
        }
    }

    function renderPrivateConversations(items) {
        const q = ($('#molChatSearchInput')?.value || '').trim().toLowerCase();
        const filtered = items.filter(item => {
            const hay = `${item.name || ''} ${item.lastMessage || ''}`.toLowerCase();
            return !q || hay.includes(q);
        });

        if (!filtered.length) {
            privateList.innerHTML = '<div class="mol-chat-empty">Aucune conversation trouvée.</div>';
            return;
        }

        privateList.innerHTML = filtered.map(item => {
            const p = presenceMeta(item);
            const unread = toInt(item.unreadCount || 0);
            return `
                <button type="button" class="mol-chat-conversation-item" data-open-thread="private" data-id="${toInt(item.id)}">
                    <span class="mol-chat-avatar-wrap">
                        <img class="mol-chat-avatar" src="${escapeHtml(normalizeAvatar(item.avatarUrl || item.avatar))}" alt="${escapeHtml(item.name || 'Conversation')}" onerror="this.src='${escapeHtml(defaultAvatar())}'">
                        <span class="mol-chat-status-dot ${p.dot}"></span>
                    </span>
                    <span class="mol-chat-conversation-body">
                        <span class="mol-chat-conversation-top">
                            <div class="mol-chat-conversation-name">${escapeHtml(item.name || 'Conversation')}</div>
                            <div class="mol-chat-conversation-time">${escapeHtml(formatTime(item.lastMessageAt))}</div>
                        </span>
                        <span class="mol-chat-conversation-preview">${escapeHtml(item.lastMessage || 'Aucun message')}</span>
                    </span>
                    ${unread > 0 ? `<span class="mol-chat-unread-pill">${unread > 99 ? '99+' : unread}</span>` : ''}
                </button>
            `;
        }).join('');
    }

    function renderGroups(items) {
        if (!items.length) {
            groupList.innerHTML = '<div class="mol-chat-empty">Aucun groupe trouvé.</div>';
            return;
        }
        groupList.innerHTML = items.map(item => `
            <button type="button" class="mol-chat-conversation-item" data-open-thread="group" data-id="${toInt(item.id)}">
                <span class="mol-chat-avatar-wrap">
                    <span class="mol-chat-group-avatar">${escapeHtml(initials(item.name))}</span>
                    <span class="mol-chat-status-dot"></span>
                </span>
                <span class="mol-chat-conversation-body">
                    <span class="mol-chat-conversation-top">
                        <span class="mol-chat-conversation-name">${escapeHtml(item.name || 'Groupe')}</span>
                        <span class="mol-chat-conversation-time">${escapeHtml(item.memberCount || 0)} membres</span>
                    </span>
                    <span class="mol-chat-conversation-preview">${escapeHtml(item.description || item.ownerName || 'Groupe de discussion')}</span>
                </span>
            </button>
        `).join('');
    }

    function switchTab(tab) {
        state.currentTab = tab;
        $$('[data-mol-tab]').forEach(btn => btn.classList.toggle('is-active', btn.dataset.molTab === tab));
        $$('[data-mol-list]').forEach(list => list.classList.toggle('is-active', list.dataset.molList === tab));
        const privateSearch = $('[data-mol-search-private]');
        const globalSearch = $('[data-mol-search-global]');
        if (privateSearch) privateSearch.hidden = tab === 'search';
        if (globalSearch) globalSearch.hidden = tab !== 'search';
        if (tab === 'private') loadPrivateConversations();
        if (tab === 'groups') loadGroups();
        if (tab === 'search') setTimeout(() => $('#molChatGlobalSearchInput')?.focus(), 80);
    }

    function threadKey(kind, id) {
        return `${kind}:${id}`;
    }

    function findItem(kind, id) {
        const list = kind === 'group' ? state.groupItems : state.privateItems;
        return list.find(item => toInt(item.id) === toInt(id)) || { id, name: kind === 'group' ? 'Groupe' : 'Conversation' };
    }

    async function openThread(kind, id) {
        const key = threadKey(kind, id);
        const existing = state.threads.get(key);
        if (existing) {
            existing.el.classList.remove('is-minimized');
            existing.el.style.zIndex = String(++state.z);
            scrollThreadBottom(existing.el);
            return existing;
        }

        const item = findItem(kind, id);
        const thread = { kind, id: toInt(id), key, item, replyTo: null, loading: false, messages: [] };
        const el = createThreadElement(thread);
        thread.el = el;
        dock.prepend(el);
        state.threads.set(key, thread);
        trimOpenThreads();
        bindThreadElement(thread);
        await loadThreadMessages(thread, true);
        subscribeThread(thread);
        return thread;
    }

    function trimOpenThreads() {
        const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
        const max = isMobile ? 1 : 3;
        const values = Array.from(state.threads.values());
        while (values.length > max) {
            const old = values.shift();
            closeThread(old);
        }
    }

    function createThreadElement(thread) {
        const p = presenceMeta(thread.item);
        const isGroup = thread.kind === 'group';
        const title = thread.item.name || (isGroup ? 'Groupe' : 'Conversation');
        const avatarHtml = isGroup
            ? `<span class="mol-chat-thread-group-avatar">${escapeHtml(initials(title))}</span>`
            : `<img class="mol-chat-thread-avatar" src="${escapeHtml(normalizeAvatar(thread.item.avatarUrl || thread.item.avatar))}" alt="${escapeHtml(title)}" onerror="this.src='${escapeHtml(defaultAvatar())}'">`;

        const safeKey = thread.key.replace(/[^a-zA-Z0-9_-]/g, '-');
        const el = document.createElement('section');
        el.className = 'mol-chat-thread';
        el.dataset.threadKey = thread.key;
        el.style.zIndex = String(++state.z);

        el.innerHTML = `
            <header class="mol-chat-thread-header">
                <div class="mol-chat-thread-user">
                    ${avatarHtml}
                    <div class="mol-chat-thread-meta">
                        <div class="mol-chat-thread-name">${escapeHtml(title)}</div>
                        <div class="mol-chat-thread-status ${p.statusClass}">${isGroup ? `${escapeHtml(thread.item.memberCount || 0)} membres` : `● ${escapeHtml(p.label)}`}</div>
                    </div>
                </div>

                <div class="mol-chat-thread-actions">
                    <div class="mol-chat-menu-wrap">
                        <button type="button" class="mol-chat-icon-btn" data-mol-menu-toggle="thread-menu-${safeKey}" aria-label="Options">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div id="thread-menu-${safeKey}" class="mol-chat-menu mol-chat-thread-menu">
                            ${isGroup ? groupThreadMenuHtml(thread) : privateThreadMenuHtml(thread)}
                        </div>
                    </div>
                    <button type="button" class="mol-chat-icon-btn" data-thread-minimize aria-label="Réduire"><i class="bi bi-dash-lg"></i></button>
                    <button type="button" class="mol-chat-icon-btn" data-thread-close aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
                </div>
            </header>

            <div class="mol-chat-thread-messages" data-thread-messages>
                <div class="mol-chat-loading">Chargement...</div>
            </div>

            <div class="mol-chat-thread-typing-host" data-thread-typing-host hidden></div>

            <div class="mol-chat-reply-preview" data-thread-reply hidden>
                <div class="mol-chat-reply-preview-body">
                    <strong data-reply-author></strong>
                    <span data-reply-text></span>
                </div>
                <button type="button" class="mol-chat-icon-btn" data-reply-clear aria-label="Annuler la réponse"><i class="bi bi-x-lg"></i></button>
            </div>

            <footer class="mol-chat-thread-composer mol-msgr-composer" data-thread-composer>
                <input type="file" data-thread-file hidden>
                <input type="file" data-thread-camera-file accept="image/*" capture="environment" hidden>

                <div class="mol-msgr-composer-row">
                    <div class="mol-msgr-left">
                        <button type="button" class="mol-msgr-icon-btn mol-msgr-toggle" data-thread-tools-toggle title="Plus" aria-label="Afficher les outils">
                            <i class="bi bi-chevron-right"></i>
                        </button>

                        <div class="mol-msgr-tools" data-thread-tools>
                            <button type="button" class="mol-msgr-icon-btn" data-thread-camera title="Caméra" aria-label="Caméra">
                                <i class="bi bi-camera-fill"></i>
                            </button>

                            <button type="button" class="mol-msgr-icon-btn" data-thread-attach title="Joindre un fichier" aria-label="Joindre un fichier">
                                <i class="bi bi-paperclip"></i>
                            </button>

                            <button type="button" class="mol-msgr-icon-btn" data-thread-voice title="Message vocal" aria-label="Message vocal">
                                <i class="bi bi-mic-fill"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mol-msgr-input-shell">
                        <textarea
                            class="mol-chat-thread-textarea mol-msgr-input"
                            rows="1"
                            placeholder="Message"
                            data-thread-input
                        ></textarea>

                        <button type="button" class="mol-msgr-emoji-btn" data-thread-emoji title="Emoji" aria-label="Emoji">
                            <span data-thread-current-emoji>😊</span>
                        </button>

                        <div class="mol-msgr-emoji-picker" data-thread-emoji-picker hidden>
                            <div class="mol-msgr-emoji-search">
                                <input type="search" placeholder="Rechercher un emoji..." data-thread-emoji-search>
                                <i class="bi bi-search"></i>
                            </div>

                            <div class="mol-msgr-emoji-tabs" data-thread-emoji-tabs></div>

                            <div class="mol-msgr-emoji-title" data-thread-emoji-title>SMILEYS & EMOTION</div>

                            <div class="mol-msgr-emoji-grid" data-thread-emoji-grid></div>
                        </div>
                    </div>

                    <button type="button" class="mol-msgr-like-btn" data-thread-like title="J’aime" aria-label="J’aime">
                        <i class="bi bi-hand-thumbs-up-fill"></i>
                    </button>

                    <button type="button" class="mol-msgr-send-btn" data-thread-send title="Envoyer" aria-label="Envoyer" hidden>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </footer>
        `;

        return el;
    }

    function privateThreadMenuHtml() {
        return `
            <button type="button" class="mol-thread-menu-item" data-thread-action="video_call">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--video">
                    <i class="bi bi-camera-video"></i>
                </span>
                <span class="mol-thread-menu-text">Appel vidéo</span>
            </button>

            <button type="button" class="mol-thread-menu-item" data-thread-action="audio_call">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--audio">
                    <i class="bi bi-telephone"></i>
                </span>
                <span class="mol-thread-menu-text">Appel audio</span>
            </button>

            <button type="button" class="mol-thread-menu-item" data-thread-action="delete">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--danger">
                    <i class="bi bi-trash"></i>
                </span>
                <span class="mol-thread-menu-text">Supprimer</span>
            </button>

            <button type="button" class="mol-thread-menu-item" data-thread-action="mark_unread">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--info">
                    <i class="bi bi-chat-left-text"></i>
                </span>
                <span class="mol-thread-menu-text">Marquer comme non lu</span>
            </button>

            <button type="button" class="mol-thread-menu-item" data-thread-action="mute">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--muted">
                    <i class="bi bi-volume-mute"></i>
                </span>
                <span class="mol-thread-menu-text">Mettre en sourdine</span>
            </button>

            <button type="button" class="mol-thread-menu-item" data-thread-action="archive">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--archive">
                    <i class="bi bi-archive"></i>
                </span>
                <span class="mol-thread-menu-text">Archiver</span>
            </button>

            <hr>

            <button type="button" class="mol-thread-menu-item mol-thread-menu-item--warning" data-thread-action="report">
                <span class="mol-thread-menu-icon mol-thread-menu-icon--warning">
                    <i class="bi bi-flag"></i>
                </span>
                <span class="mol-thread-menu-text">Signaler</span>
            </button>
        `;
    }

    function getPrivateThreadConversationId(element) {
        const thread =
            element.closest('[data-conversation-id]') ||
            element.closest('[data-thread-id]') ||
            element.closest('.mol-chat-thread') ||
            element.closest('.mol-chat-toast') ||
            element.closest('.toast');

        if (!thread) {
            return Number(window.CHAT?.currentMobileConversationId || 0);
        }

        if (thread.dataset.conversationId) {
            return Number(thread.dataset.conversationId || 0);
        }

        if (thread.dataset.threadId) {
            return Number(thread.dataset.threadId || 0);
        }

        if (thread.id && thread.id.startsWith('molToast-')) {
            return Number(thread.id.replace('molToast-', '') || 0);
        }

        return Number(window.CHAT?.currentMobileConversationId || 0);
    }

    function closePrivateThreadMenu(element) {
        const menu = element.closest('.mol-chat-menu, .mol-chat-dropdown-menu, .dropdown-menu');

        if (menu) {
            menu.classList.remove('is-open', 'show');
            menu.setAttribute('aria-hidden', 'true');
        }

        const dropdown = element.closest('.dropdown');

        if (dropdown) {
            const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');

            if (toggle && window.bootstrap?.Dropdown) {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            }
        }

        document
            .querySelectorAll('.mol-chat-menu.is-open, .mol-chat-dropdown-menu.is-open')
            .forEach((item) => item.classList.remove('is-open'));
    }

    async function runPrivateThreadMenuAction(event, button) {
        const action = button.dataset.threadAction;
        const conversationId = getPrivateThreadConversationId(button);

        if (!conversationId || !action) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        closePrivateThreadMenu(button);

        if (action === 'video_call') {
            if (typeof window.MOLStartConversationCall === 'function') {
                window.MOLStartConversationCall(conversationId, 'video');
            }
            return;
        }

        if (action === 'audio_call') {
            if (typeof window.MOLStartConversationCall === 'function') {
                window.MOLStartConversationCall(conversationId, 'audio');
            }
            return;
        }

        const actionMap = {
            delete: 'deleteConversation',
            mark_unread: 'markUnread',
            mute: 'mute',
            archive: 'archive',
            report: 'report'
        };

        const mappedAction = actionMap[action];

        if (!mappedAction) {
            return;
        }

        if (typeof window.MOLChatRunAction === 'function') {
            await window.MOLChatRunAction(event, conversationId, mappedAction);
        }
    }

    function groupThreadMenuHtml() {
        return `
            <button type="button" data-thread-action="refresh"><i class="bi bi-arrow-clockwise"></i><span>Refresh</span></button>
            <button type="button" data-thread-action="search"><i class="bi bi-search"></i><span>Search in group</span></button>
            <button type="button" data-thread-action="mute_local"><i class="bi bi-volume-mute"></i><span>Muted locally</span></button>
            <hr>
            <button type="button" data-thread-action="report_group"><i class="bi bi-flag"></i><span>Report</span></button>
        `;
    }

    const molEmojiPickers = new WeakMap();

    function waitForEmojiButton(callback) {
        if (window.EmojiButton) {
            callback();
            return;
        }

        window.addEventListener('mol:emoji-button-ready', callback, { once: true });

        let tries = 0;
        const timer = setInterval(() => {
            tries++;

            if (window.EmojiButton) {
                clearInterval(timer);
                callback();
            }

            if (tries > 40) {
                clearInterval(timer);
                console.warn('[MOL CHAT] EmojiButton non chargé.');
            }
        }, 100);
    }

    function getEmojiValue(selection) {
        if (!selection) {
            return '';
        }

        if (typeof selection === 'string') {
            return selection;
        }

        return selection.emoji || selection.unicode || selection.name || '';
    }

    function initThreadEmojiButton(thread) {
        const el = thread.el;
        const trigger = $('[data-thread-emoji]', el);
        const input = $('[data-thread-input]', el);

        if (!trigger || !input) {
            return;
        }

        if (molEmojiPickers.has(trigger)) {
            return;
        }

        waitForEmojiButton(() => {
            if (!window.EmojiButton) {
                return;
            }

            const picker = new window.EmojiButton({
                position: 'top-end',
                theme: 'light',
                autoHide: false,
                showPreview: false,
                showSearch: true,
                showRecents: true,
                emojiSize: '1.35em',
                emojisPerRow: 8,
                rows: 6,
                zIndex: 2147483647,
            });

            picker.on('emoji', selection => {
                const emoji = getEmojiValue(selection);

                if (!emoji) {
                    return;
                }

                insertAtCursor(input, emoji);

                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));

                input.focus();
            });

            trigger.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                picker.togglePicker(trigger);
            });

            molEmojiPickers.set(trigger, picker);
        });
    }

    let molChatCameraStream = null;
    let molChatCameraThread = null;

    function ensureMolChatCameraOverlay() {
        let overlay = document.getElementById('molChatCameraOverlay');

        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = 'molChatCameraOverlay';
        overlay.className = 'mol-chat-camera-overlay is-hidden';

        overlay.innerHTML = `
            <div class="mol-chat-camera-backdrop" data-camera-close></div>

            <section class="mol-chat-camera-shell" role="dialog" aria-modal="true" aria-label="Appareil photo">
                <header class="mol-chat-camera-header">
                    <div>
                        <strong>Appareil photo</strong>
                        <small>Capture une photo et envoie-la directement</small>
                    </div>

                    <button type="button" class="mol-chat-camera-close" data-camera-close aria-label="Fermer">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </header>

                <main class="mol-chat-camera-stage">
                    <video id="molChatCameraVideo" class="mol-chat-camera-video" autoplay playsinline muted></video>

                    <div class="mol-chat-camera-empty" id="molChatCameraEmpty">
                        <i class="bi bi-camera-fill"></i>
                        <span>Préparation de la caméra...</span>
                    </div>

                    <canvas id="molChatCameraCanvas" hidden></canvas>
                </main>

                <footer class="mol-chat-camera-footer">
                    <button type="button" class="mol-chat-camera-secondary" data-camera-switch>
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Changer</span>
                    </button>

                    <button type="button" class="mol-chat-camera-capture" data-camera-capture aria-label="Capturer">
                        <i class="bi bi-camera-fill"></i>
                    </button>

                    <button type="button" class="mol-chat-camera-secondary" data-camera-fallback>
                        <i class="bi bi-image"></i>
                        <span>Galerie</span>
                    </button>
                </footer>
            </section>
        `;

        document.body.appendChild(overlay);

        overlay.addEventListener('click', async (event) => {
            if (event.target.closest('[data-camera-close]')) {
                event.preventDefault();
                closeMolChatCamera();
                return;
            }

            if (event.target.closest('[data-camera-capture]')) {
                event.preventDefault();
                await captureMolChatPhoto();
                return;
            }

            if (event.target.closest('[data-camera-switch]')) {
                event.preventDefault();
                await switchMolChatCamera();
                return;
            }

            if (event.target.closest('[data-camera-fallback]')) {
                event.preventDefault();

                const thread = molChatCameraThread;
                closeMolChatCamera();

                if (thread?.el) {
                    $('[data-thread-camera-file]', thread.el)?.click();
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !overlay.classList.contains('is-hidden')) {
                closeMolChatCamera();
            }
        });

        return overlay;
    }

    let molChatCameraFacingMode = 'environment';

    async function openMolChatCamera(thread) {
        molChatCameraThread = thread;

        const overlay = ensureMolChatCameraOverlay();
        const video = document.getElementById('molChatCameraVideo');
        const empty = document.getElementById('molChatCameraEmpty');

        overlay.classList.remove('is-hidden');
        overlay.classList.add('is-open');
        document.body.classList.add('mol-chat-camera-open');

        if (empty) {
            empty.hidden = false;
            empty.querySelector('span').textContent = 'Préparation de la caméra...';
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            notify('Caméra non supportée sur cet appareil.', 'error');

            closeMolChatCamera();

            if (thread?.el) {
                $('[data-thread-camera-file]', thread.el)?.click();
            }

            return;
        }

        try {
            stopMolChatCameraStream();

            molChatCameraStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: molChatCameraFacingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            });

            if (video) {
                video.srcObject = molChatCameraStream;
                await video.play().catch(() => {});
            }

            if (empty) {
                empty.hidden = true;
            }
        } catch (error) {
            console.error('[MOL CAMERA]', error);

            notify('Impossible d’ouvrir la caméra. Sélectionne une image.', 'error');

            closeMolChatCamera();

            if (thread?.el) {
                $('[data-thread-camera-file]', thread.el)?.click();
            }
        }
    }

    async function switchMolChatCamera() {
        molChatCameraFacingMode = molChatCameraFacingMode === 'environment' ? 'user' : 'environment';

        if (molChatCameraThread) {
            await openMolChatCamera(molChatCameraThread);
        }
    }

    async function captureMolChatPhoto() {
        const thread = molChatCameraThread;
        const video = document.getElementById('molChatCameraVideo');
        const canvas = document.getElementById('molChatCameraCanvas');

        if (!thread || !video || !canvas) {
            notify('Capture impossible.', 'error');
            return;
        }

        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 720;

        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        if (!context) {
            notify('Capture impossible.', 'error');
            return;
        }

        context.drawImage(video, 0, 0, width, height);

        canvas.toBlob(async (blob) => {
            if (!blob) {
                notify('Photo vide.', 'error');
                return;
            }

            const file = new File(
                [blob],
                `photo-${Date.now()}.jpg`,
                { type: 'image/jpeg' }
            );

            closeMolChatCamera();

            try {
                await sendThreadFile(thread, file);
            } catch (error) {
                notify(error.message || 'Photo non envoyée.', 'error');
            }
        }, 'image/jpeg', 0.92);
    }

    function stopMolChatCameraStream() {
        if (molChatCameraStream) {
            molChatCameraStream.getTracks().forEach((track) => track.stop());
            molChatCameraStream = null;
        }

        const video = document.getElementById('molChatCameraVideo');

        if (video) {
            video.srcObject = null;
        }
    }

    function closeMolChatCamera() {
        const overlay = document.getElementById('molChatCameraOverlay');

        stopMolChatCameraStream();

        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.classList.add('is-hidden');
        }

        document.body.classList.remove('mol-chat-camera-open');

        molChatCameraThread = null;
    }

    const molTypingTimers = new Map();

    function renderTypingIndicatorHtml(thread, userName = '') {
        const avatar = escapeHtml(thread?.avatar || thread?.otherAvatar || thread?.participantAvatar || '');
        const label = userName ? `${escapeHtml(userName)} is typing...` : 'Typing...';

        return `
            <div class="mol-chat-typing-row">
                <div class="mol-chat-typing-avatar">
                    ${avatar
                        ? `<img src="${avatar}" alt="">`
                        : `<span><i class="bi bi-person-fill"></i></span>`
                    }
                </div>

                <div class="mol-chat-typing-bubble" aria-label="${label}">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
    }

    function showTypingIndicator(thread, userName = '') {
        if (!thread?.el) return;

        const host = thread.el.querySelector('[data-thread-typing-host]');
        if (!host) return;

        host.innerHTML = renderTypingIndicatorHtml(thread, userName);
        host.hidden = false;
        host.classList.add('is-visible');

        const key = `${thread.kind}:${thread.id}`;

        if (molTypingTimers.has(key)) {
            clearTimeout(molTypingTimers.get(key));
        }

        const timer = setTimeout(() => {
            hideTypingIndicator(thread);
        }, 2600);

        molTypingTimers.set(key, timer);
    }

    function hideTypingIndicator(thread) {
        if (!thread?.el) return;

        const host = thread.el.querySelector('[data-thread-typing-host]');
        if (!host) return;

        host.hidden = true;
        host.classList.remove('is-visible');
        host.innerHTML = '';

        const key = `${thread.kind}:${thread.id}`;

        if (molTypingTimers.has(key)) {
            clearTimeout(molTypingTimers.get(key));
            molTypingTimers.delete(key);
        }
    }

    function bindThreadElement(thread) {
        const el = thread.el;

        // Remettre au premier plan quand on clique sur la fenêtre
        el.addEventListener('mousedown', () => {
            el.style.zIndex = String(++state.z);
        });

        // Réduire la fenêtre
        $('[data-thread-minimize]', el)?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            closeAllMenus();
            el.classList.toggle('is-minimized');
        });

        // Fermer la fenêtre
        $('[data-thread-close]', el)?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            closeAllMenus();
            closeThread(thread);
        });

        $('[data-thread-send]', el)?.addEventListener('click', () => sendThreadMessage(thread));

        $('[data-thread-tools-toggle]', el)?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const composer = $('[data-thread-composer]', el);
            const tools = $('[data-thread-tools]', el);
            const chevron = $('[data-thread-tools-toggle] i', el);

            if (!composer || !tools) return;

            const willOpen = !composer.classList.contains('is-tools-open');

            composer.classList.toggle('is-tools-open', willOpen);
            tools.hidden = !willOpen;

            chevron?.classList.toggle('bi-chevron-right', !willOpen);
            chevron?.classList.toggle('bi-chevron-left', willOpen);
        });

        $('[data-thread-camera]', el)?.addEventListener('click', (event) => {
            event.preventDefault();
            openMolChatCamera(thread);
        });

        $('[data-thread-camera-file]', el)?.addEventListener('change', e => {
            const file = e.target.files?.[0];

            if (file) {
                sendThreadFile(thread, file);
            }

            e.target.value = '';
        });

        $('[data-thread-attach]', el)?.addEventListener('click', () => {
            $('[data-thread-file]', el)?.click();
        });

        $('[data-thread-file]', el)?.addEventListener('change', e => {
            const file = e.target.files?.[0];

            if (file) {
                sendThreadFile(thread, file);
            }

            e.target.value = '';
        });

        $('[data-thread-voice]', el)?.addEventListener('click', () => toggleVoice(thread));

        initThreadEmojiButton(thread);

        $('[data-thread-like]', el)?.addEventListener('click', async () => {
            const input = $('[data-thread-input]', el);
            const composer = $('[data-thread-composer]', el);
            const tools = $('[data-thread-tools]', el);

            if (composer && tools) {
                composer.classList.remove('is-tools-open');
                tools.hidden = true;
            }
            if (!input) return;

            input.value = '👍';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            await sendThreadMessage(thread);

            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const input = $('[data-thread-input]', el);

        input?.addEventListener('input', () => {
            autoResize(input);

            const hasText = input.value.trim().length > 0;

            const sendBtn = $('[data-thread-send]', el);
            const likeBtn = $('[data-thread-like]', el);

            if (sendBtn) sendBtn.hidden = !hasText;
            if (likeBtn) likeBtn.hidden = hasText;

            if (thread.kind === 'private') {
                handleTyping(thread);
            }
        });

        input?.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendThreadMessage(thread);
            }
        });
    }

    function insertAtCursor(input, text) {
        if (!input) return;
        const start = input.selectionStart || input.value.length;
        const end = input.selectionEnd || input.value.length;
        input.value = input.value.slice(0, start) + text + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + text.length;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
    }

    function autoResize(input) {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 92) + 'px';
    }

    
    function readMessengerRecentEmojis() {
        try {
            const items = JSON.parse(localStorage.getItem('mol_msgr_recent_emojis') || '[]');
            return Array.isArray(items) ? items.slice(0, 24) : [];
        } catch (_) {
            return [];
        }
    }

    function saveMessengerRecentEmojis(items) {
        localStorage.setItem('mol_msgr_recent_emojis', JSON.stringify(items.slice(0, 24)));
    }

    function getMessengerSelectedEmoji() {
        return localStorage.getItem('mol_msgr_selected_emoji') || '😊';
    }

    function setMessengerSelectedEmoji(emoji) {
        localStorage.setItem('mol_msgr_selected_emoji', emoji || '😊');
    }

    

    function closeThread(thread) {
        if (!thread) return;
        thread.el?.remove();
        state.threads.delete(thread.key);
        const es = state.eventSources.get(thread.key);
        if (es) es.close();
        state.eventSources.delete(thread.key);
        const rec = state.recorders.get(thread.key);
        if (rec?.stream) rec.stream.getTracks().forEach(track => track.stop());
        state.recorders.delete(thread.key);
    }

    async function loadThreadMessages(thread, scroll = false) {
        if (thread.loading) return;
        thread.loading = true;
        const box = $('[data-thread-messages]', thread.el);
        try {
            const endpoint = thread.kind === 'group' ? tpl(groupUrls.messages, thread.id) : tpl(urls.messages, thread.id);
            const url = new URL(buildUrl(endpoint));
            url.searchParams.set('limit', '100');
            url.searchParams.set('_ts', Date.now().toString());
            const data = await fetchJson(url.toString());
            thread.messages = data.items || [];
            renderThreadMessages(thread);
            if (thread.kind === 'private' && urls.read) {
                fetchJson(tpl(urls.read, thread.id), { method: 'POST' }).catch(() => {});
            }
            if (scroll) scrollThreadBottom(thread.el);
        } catch (error) {
            if (box) box.innerHTML = `<div class="mol-chat-empty">${escapeHtml(error.message || 'Erreur de chargement.')}</div>`;
        } finally {
            thread.loading = false;
        }
    }

    function renderThreadMessages(thread) {
        const box = $('[data-thread-messages]', thread.el);
        if (!box) return;

        if (!thread.messages.length) {
            box.innerHTML = '<div class="mol-chat-empty">Aucun message.</div>';
            return;
        }

        const dateLabel = new Date().toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' });
        box.innerHTML = `<div class="mol-chat-day-separator">${escapeHtml(dateLabel)}</div>` + thread.messages.map(message => renderMessage(thread, message)).join('');

        if (state.typingVisible.has(thread.key)) {
            appendTyping(thread);
        }
    }

    function renderMessage(thread, message) {
        const mine = !!message.mine;
        const avatar = normalizeAvatar(message.avatarUrl || (mine ? cfg.meAvatar : thread.item.avatarUrl));
        const content = message.deleted ? 'Message supprimé' : (message.content || '');
        const status = mine ? renderStatus(message) : '';
        const reply = renderReplyQuote(message.replyTo);
        const attachment = renderAttachment(message);
        const reactions = renderReactions(message.reactions || []);
        const safeContent = escapeHtml(previewMessage(message));
        const canDelete = mine || message.canDelete;

        return `
            <div class="mol-chat-message-row ${mine ? 'is-me' : 'is-them'}"
                 data-message-id="${toInt(message.id)}"
                 data-message-content="${safeContent}"
                 data-message-author="${escapeHtml(message.authorName || (mine ? 'Vous' : thread.item.name || 'Contact'))}"
                 data-message-type="${escapeHtml(message.type || 'text')}">
                ${!mine ? `<img class="mol-chat-message-avatar" src="${escapeHtml(avatar)}" alt="" onerror="this.src='${escapeHtml(defaultAvatar())}'">` : ''}
                <div class="mol-chat-message-stack">
                    <div class="mol-chat-bubble">
                        ${reply}
                        ${content ? `<div>${escapeHtml(content)}</div>` : ''}
                        ${attachment}
                    </div>
                    ${reactions}
                    <div class="mol-chat-message-meta">
                        <span>${escapeHtml(message.createdAt || '')}</span>
                        ${status}
                        <span class="mol-chat-message-actions">
                            <button type="button" class="mol-chat-message-action" title="Répondre" data-message-action="reply"><i class="bi bi-reply-fill"></i></button>
                            
                            <button
                                type="button"
                                class="mol-chat-message-action mol-chat-message-react-btn"
                                title="Réagir"
                                aria-label="Réagir"
                                data-message-action="react"
                            >
                                <i class="bi bi-emoji-smile"></i>
                            </button>
                            ${canDelete ? `<button type="button" class="mol-chat-message-action" title="Supprimer" data-message-action="delete"><i class="bi bi-trash"></i></button>` : ''}
                        </span>
                    </div>
                </div>
                ${mine ? `<img class="mol-chat-message-avatar" src="${escapeHtml(avatar)}" alt="" onerror="this.src='${escapeHtml(defaultAvatar())}'">` : ''}
            </div>
        `;
    }

    function renderStatus(message) {
        const s = message.status || (message.readAt ? 'read' : message.deliveredAt ? 'delivered' : 'sent');
        if (s === 'read') return '<i class="bi bi-check2-all" title="Lu"></i>';
        if (s === 'delivered') return '<i class="bi bi-check2-all" title="Livré"></i>';
        return '<i class="bi bi-check2" title="Envoyé"></i>';
    }

    function renderReplyQuote(reply) {
        if (!reply || !reply.id) return '';
        return `
            <span class="mol-chat-reply-quote" data-scroll-reply="${toInt(reply.id)}">
                <strong>${escapeHtml(reply.authorName || 'Message')}</strong>
                <span>${escapeHtml(reply.content || '')}</span>
            </span>
        `;
    }

    function previewMessage(message) {
        if (message.content) return message.content;
        const type = String(message.type || 'text');
        if (['voice', 'audio'].includes(type)) return 'Message vocal';
        if (type === 'image') return 'Photo';
        if (type === 'video') return 'Vidéo';
        if (type === 'file') return message.attachmentName || message.meta?.originalName || 'Fichier';
        return 'Message';
    }


    function fileExtension(name) {
        const clean = String(name || '').split('?')[0].split('#')[0];
        const parts = clean.split('.');

        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function fileKindFrom(name, mime, type) {
        const ext = fileExtension(name);
        const m = String(mime || '').toLowerCase();
        const t = String(type || '').toLowerCase();

        if (m.startsWith('image/') || t === 'image' || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'].includes(ext)) {
            return 'image';
        }

        if (m.startsWith('video/') || t === 'video' || ['mp4', 'webm', 'mov', 'avi', 'mkv'].includes(ext)) {
            return 'video';
        }

        if (m.startsWith('audio/') || t === 'voice' || t === 'audio' || ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'].includes(ext)) {
            return 'audio';
        }

        if (m === 'application/pdf' || ext === 'pdf') {
            return 'pdf';
        }

        if (['doc', 'docx', 'odt', 'rtf'].includes(ext)) {
            return 'word';
        }

        if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) {
            return 'excel';
        }

        if (['ppt', 'pptx', 'odp'].includes(ext)) {
            return 'powerpoint';
        }

        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
            return 'archive';
        }

        if (['txt', 'md', 'log'].includes(ext)) {
            return 'text';
        }

        return 'file';
    }

    function fileIconForKind(kind) {
        const icons = {
            image: 'bi-file-earmark-image',
            video: 'bi-file-earmark-play',
            audio: 'bi-file-earmark-music',
            pdf: 'bi-file-earmark-pdf',
            word: 'bi-file-earmark-word',
            excel: 'bi-file-earmark-spreadsheet',
            powerpoint: 'bi-file-earmark-slides',
            archive: 'bi-file-earmark-zip',
            text: 'bi-file-earmark-text',
            file: 'bi-file-earmark'
        };

        return icons[kind] || icons.file;
    }

    function fileLabelForKind(kind) {
        const labels = {
            image: 'Image',
            video: 'Vidéo',
            audio: 'Audio',
            pdf: 'PDF',
            word: 'Document Word',
            excel: 'Tableur',
            powerpoint: 'Présentation',
            archive: 'Archive',
            text: 'Texte',
            file: 'Fichier'
        };

        return labels[kind] || labels.file;
    }

    function renderFileDownloadBar({ name, size, absolute, kind }) {
        const icon = fileIconForKind(kind);
        const label = fileLabelForKind(kind);

        return `
            <div class="mol-filebar">
                <div class="mol-filebar__icon mol-filebar__icon--${escapeHtml(kind)}">
                    <i class="bi ${escapeHtml(icon)}"></i>
                </div>

                <div class="mol-filebar__main">
                    <div class="mol-filebar__name">${escapeHtml(name)}</div>
                    <div class="mol-filebar__meta">
                        <span>${escapeHtml(label)}</span>
                        ${size ? `<span>•</span><span>${escapeHtml(size)}</span>` : ''}
                    </div>
                </div>

                <a
                    class="mol-filebar__download"
                    href="${escapeHtml(absolute)}"
                    download="${escapeHtml(name)}"
                    target="_blank"
                    rel="noopener"
                    title="Télécharger"
                    aria-label="Télécharger"
                >
                    <i class="bi bi-download"></i>
                </a>
            </div>
        `;
    }

    function renderAttachment(message) {
        const url = message.attachmentUrl || message.meta?.url;

        if (!url) return '';

        const meta = message.meta || {};
        const name = meta.originalName || meta.filename || message.attachmentName || message.content || 'Fichier';
        const mime = String(meta.mimeType || '').toLowerCase();
        const type = String(message.type || '').toLowerCase();
        const size = bytesToSize(meta.size || meta.filesize);
        const absolute = buildUrl(url);
        const kind = fileKindFrom(name, mime, type);

        const bar = renderFileDownloadBar({
            name,
            size,
            absolute,
            kind
        });

        if (kind === 'image') {
            return `
                <div class="mol-file-preview mol-file-preview--image">
                    <a href="${escapeHtml(absolute)}" target="_blank" rel="noopener" class="mol-file-preview__image-link">
                        <img
                            class="mol-chat-image mol-file-preview__image"
                            src="${escapeHtml(absolute)}"
                            alt="${escapeHtml(name)}"
                            loading="lazy"
                        >
                    </a>
                    ${bar}
                </div>
            `;
        }

        if (kind === 'video') {
            return `
                <div class="mol-file-preview mol-file-preview--video">
                    <video
                        class="mol-file-preview__video"
                        controls
                        preload="metadata"
                        src="${escapeHtml(absolute)}"
                    ></video>
                    ${bar}
                </div>
            `;
        }

        if (kind === 'audio') {
            return `
                <div class="mol-file-preview mol-file-preview--audio">
                    <div class="mol-audio-card">
                        <div class="mol-audio-card__icon">
                            <i class="bi bi-mic-fill"></i>
                        </div>
                        <audio class="mol-chat-audio mol-audio-card__player" controls preload="metadata" src="${escapeHtml(absolute)}"></audio>
                    </div>
                    ${bar}
                </div>
            `;
        }

        if (kind === 'pdf') {
            return `
                <div class="mol-file-preview mol-file-preview--pdf">
                    <div class="mol-pdf-preview">
                        <iframe
                            src="${escapeHtml(absolute)}#toolbar=0&navpanes=0"
                            title="${escapeHtml(name)}"
                            loading="lazy"
                        ></iframe>
                    </div>
                    ${bar}
                </div>
            `;
        }

        return `
            <div class="mol-file-preview mol-file-preview--document mol-file-preview--${escapeHtml(kind)}">
                <a class="mol-doc-card" href="${escapeHtml(absolute)}" target="_blank" rel="noopener">
                    <div class="mol-doc-card__icon mol-doc-card__icon--${escapeHtml(kind)}">
                        <i class="bi ${escapeHtml(fileIconForKind(kind))}"></i>
                    </div>

                    <div class="mol-doc-card__content">
                        <strong>${escapeHtml(name)}</strong>
                        <span>
                            ${escapeHtml(fileLabelForKind(kind))}
                            ${size ? ` • ${escapeHtml(size)}` : ''}
                        </span>
                    </div>

                    <div class="mol-doc-card__arrow">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </div>
                </a>

                ${bar}
            </div>
        `;
        }

        function renderReactions(reactions) {
            if (!reactions || !reactions.length) return '';
            return `<div class="mol-chat-reactions">${reactions.map(r => `
                <span class="mol-chat-reaction-chip"><span>${escapeHtml(r.emoji)}</span><span>${toInt(r.total ?? r.count ?? 1)}</span></span>
            `).join('')}</div>`;
        }

        function scrollThreadBottom(el) {
            const box = $('[data-thread-messages]', el);
            if (box) box.scrollTop = box.scrollHeight;
        }

        async function sendThreadMessage(thread) {
            const input = $('[data-thread-input]', thread.el);
            if (!input) return;
            const content = input.value.trim();
            if (!content) return;
            input.disabled = true;
            try {
                const endpoint = thread.kind === 'group' ? tpl(groupUrls.send, thread.id) : tpl(urls.send, thread.id);
                await fetchJson(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content, replyToId: thread.replyTo?.id || null }),
                });
                input.value = '';
                autoResize(input);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                clearReply(thread);
                await loadThreadMessages(thread, true);
                if (thread.kind === 'private') loadPrivateConversations(); else loadGroups();
                updateUnreadBadge();
            } catch (error) {
                notify(error.message || 'Message non envoyé.', 'error');
            } finally {
                input.disabled = false;
                input.focus();
            }
        }

        async function sendThreadFile(thread, file) {
            const input = $('[data-thread-file]', thread.el);
            if (input) input.value = '';
            if (!file) return;

            const fd = new FormData();
            fd.append('file', file);
            if (thread.replyTo?.id) fd.append('replyToId', String(thread.replyTo.id));

            try {
                const endpoint = thread.kind === 'group' ? tpl(groupUrls.sendFile, thread.id) : tpl(urls.sendFile, thread.id);
                await fetchJson(endpoint, { method: 'POST', body: fd });
                clearReply(thread);
                await loadThreadMessages(thread, true);
                if (thread.kind === 'private') loadPrivateConversations(); else loadGroups();
            } catch (error) {
                notify(error.message || 'Fichier non envoyé.', 'error');
            }
        }

        async function toggleVoice(thread) {
            const key = thread.key;
            const button = $('[data-thread-voice]', thread.el);
            const current = state.recorders.get(key);

            if (current?.recorder?.state === 'recording') {
                current.recorder.stop();
                button?.classList.remove('is-recording');
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                notify('Le vocal n’est pas supporté sur cet appareil.', 'error');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
                const recorder = new MediaRecorder(stream, { mimeType });
                const chunks = [];

                recorder.ondataavailable = event => {
                    if (event.data && event.data.size > 0) chunks.push(event.data);
                };

                recorder.onstop = async () => {
                    button?.classList.remove('is-recording');
                    stream.getTracks().forEach(track => track.stop());
                    state.recorders.delete(key);

                    const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                    if (!blob.size) return;

                    const file = new File([blob], `voice-${Date.now()}.webm`, { type: blob.type || 'audio/webm' });
                    const fd = new FormData();
                    if (thread.kind === 'private') fd.append('voice', file);
                    else fd.append('file', file);
                    if (thread.replyTo?.id) fd.append('replyToId', String(thread.replyTo.id));

                    try {
                        const endpoint = thread.kind === 'private' ? tpl(urls.sendVoice, thread.id) : tpl(groupUrls.sendFile, thread.id);
                        await fetchJson(endpoint, { method: 'POST', body: fd });
                        clearReply(thread);
                        await loadThreadMessages(thread, true);
                    } catch (error) {
                        notify(error.message || 'Vocal non envoyé.', 'error');
                    }
                };

                state.recorders.set(key, { recorder, stream });
                recorder.start();
                button?.classList.add('is-recording');
                notify('Enregistrement vocal démarré. Clique encore pour arrêter.');
            } catch (error) {
                notify('Impossible d’ouvrir le micro.', 'error');
            }
        }

        function prepareReply(thread, messageRow) {
            thread.replyTo = {
                id: toInt(messageRow.dataset.messageId),
                authorName: messageRow.dataset.messageAuthor || 'Message',
                content: messageRow.dataset.messageContent || 'Message',
                type: messageRow.dataset.messageType || 'text',
            };
            renderReplyPreview(thread);
            $('[data-thread-input]', thread.el)?.focus();
        }

        function clearReply(thread) {
            thread.replyTo = null;
            renderReplyPreview(thread);
        }

        function renderReplyPreview(thread) {
            const box = $('[data-thread-reply]', thread.el);
            if (!box) return;
            if (!thread.replyTo) {
                box.classList.remove('is-active');
                box.hidden = true;
                return;
            }
            box.hidden = false;
            box.classList.add('is-active');
            $('[data-reply-author]', box).textContent = thread.replyTo.authorName || 'Message';
            $('[data-reply-text]', box).textContent = thread.replyTo.content || '';
        }


        const molMessageReaction = {
            picker: null,
            target: null,
            ready: false,
            loading: null
        };

        function getEmojiButtonCtor() {
            if (typeof window.EmojiButton === 'function') {
                return window.EmojiButton;
            }

            if (typeof window.EmojiButton?.EmojiButton === 'function') {
                return window.EmojiButton.EmojiButton;
            }

            return null;
        }

        async function ensureEmojiButtonLoaded() {
            if (getEmojiButtonCtor()) {
                return;
            }

            if (molMessageReaction.loading) {
                return molMessageReaction.loading;
            }

            molMessageReaction.loading = new Promise((resolve, reject) => {
                const script = document.createElement('script');

                script.src = 'https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js';
                script.async = true;
                script.onload = resolve;
                script.onerror = reject;

                document.head.appendChild(script);
            });

            return molMessageReaction.loading;
        }

        async function ensureMessageReactionPicker() {
            await ensureEmojiButtonLoaded();

            if (molMessageReaction.ready && molMessageReaction.picker) {
                return molMessageReaction.picker;
            }

            const Ctor = getEmojiButtonCtor();

            if (!Ctor) {
                throw new Error('EmojiButton indisponible.');
            }

            molMessageReaction.picker = new Ctor({
                position: 'top-start',
                autoHide: true,
                theme: 'light',
                rootElement: document.body,
                zIndex: 2147483647,
                showPreview: false,
                showSearch: true,
                showRecents: true,
                emojiSize: '1.35em',
                emojisPerRow: 8,
                rows: 5
            });

            molMessageReaction.picker.on('emoji', async (selection) => {
                const emoji = typeof selection === 'string'
                    ? selection
                    : selection?.emoji;

                const target = molMessageReaction.target;

                if (!emoji || !target?.thread || !target?.messageId) {
                    return;
                }

                try {
                    await sendMessageReaction(target.thread, target.messageId, emoji);
                } catch (error) {
                    console.error('[MOL REACTION]', error);
                    notify(error.message || 'Réaction impossible.', 'error');
                } finally {
                    molMessageReaction.target = null;
                }
            });

            molMessageReaction.ready = true;

            return molMessageReaction.picker;
        }

        async function sendMessageReaction(thread, messageId, emoji) {
            const endpoint = thread.kind === 'group'
                ? tpl(groupUrls.react, messageId)
                : tpl(urls.react, messageId);

            if (!endpoint) {
                throw new Error('Route de réaction manquante.');
            }

            await fetchJson(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    emoji
                }),
            });

            await loadThreadMessages(thread, false);
        }


        async function reactToMessage(thread, messageId, trigger = null) {
            if (!thread || !messageId) {
                return;
            }

            try {
                const picker = await ensureMessageReactionPicker();

                molMessageReaction.target = {
                    thread,
                    messageId
                };

                if (trigger) {
                    picker.togglePicker(trigger);
                    return;
                }

                const row = thread.el?.querySelector(`[data-message-id="${Number(messageId)}"]`);
                const button = row?.querySelector('[data-message-action="react"]');

                picker.togglePicker(button || row || document.body);
            } catch (error) {
                console.error('[MOL REACTION PICKER]', error);
                notify(error.message || 'Emoji indisponible.', 'error');
            }
        }

        async function deleteMessage(thread, messageId) {
            const ok = await askConfirm('Supprimer ce message ?', 'Cette action masquera le message dans la conversation.');
            if (!ok) return;
            try {
                const endpoint = thread.kind === 'group' ? tpl(groupUrls.deleteMessage, messageId) : tpl(urls.messageDelete, messageId);
                await fetchJson(endpoint, { method: thread.kind === 'group' ? 'POST' : 'DELETE' });
                await loadThreadMessages(thread, false);
                notify('Message supprimé.', 'success');
            } catch (error) {
                notify(error.message || 'Suppression impossible.', 'error');
            }
        }

        async function runThreadAction(thread, action) {
            closeAllMenus();

            if (action === 'refresh') {
                await loadThreadMessages(thread, true);
                return;
            }

            if (action === 'search') {
                const q = await askText({ title: 'Rechercher dans le groupe', placeholder: 'Mot-clé' });
                if (q) searchInThread(thread, q);
                return;
            }

            if (action === 'mute_local') {
                notify('Groupe mis en sourdine localement.', 'success');
                return;
            }

            if (action === 'report_group') {
                const reason = await askText({ title: 'Signaler ce groupe', placeholder: 'Raison du signalement', multiline: true, confirm: 'Signaler' });
                if (reason !== null) notify('Signalement enregistré côté interface. Ajoute une route serveur si tu veux une modération avancée.', 'success');
                return;
            }

            if (action === 'video_call' || action === 'audio_call') {
                await startCall(thread, action === 'video_call' ? 'video' : 'audio');
                return;
            }

            if (action === 'delete') {
                const ok = await askConfirm('Supprimer la conversation ?', 'Tous les messages visibles seront masqués.');
                if (!ok) return;
                await conversationAction(thread, urls.conversationDelete, 'Conversation supprimée.');
                closeThread(thread);
                await loadPrivateConversations();
                return;
            }

            if (action === 'mark_unread') {
                await conversationAction(thread, urls.markUnread, 'Conversation marquée comme non lue.');
                await loadPrivateConversations();
                return;
            }

            if (action === 'mute') {
                await conversationAction(thread, urls.mute, 'Sourdine mise à jour.');
                return;
            }

            if (action === 'archive') {
                await conversationAction(thread, urls.archive, 'Conversation archivée.');
                closeThread(thread);
                await loadPrivateConversations();
                return;
            }

            if (action === 'report') {
                const reason = await askText({ title: 'Signaler cette conversation', placeholder: 'Explique brièvement le problème', multiline: true, confirm: 'Signaler' });
                if (reason === null) return;
                await conversationAction(thread, urls.report, 'Signalement envoyé.', { reason });
            }
        }

        async function conversationAction(thread, template, successMessage, payload = {}) {
            if (!template) {
                notify('Route manquante pour cette action.', 'error');
                return;
            }
            try {
                await fetchJson(tpl(template, thread.id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                notify(successMessage, 'success');
            } catch (error) {
                notify(error.message || 'Action impossible.', 'error');
            }
        }

        async function searchInThread(thread, query) {
            const q = String(query || '').trim();
            if (q.length < 2) return;
            try {
                const endpoint = thread.kind === 'group' ? tpl(groupUrls.search, thread.id) : tpl(urls.searchMessages, thread.id);
                const url = new URL(buildUrl(endpoint));
                url.searchParams.set('q', q);
                const data = await fetchJson(url.toString());
                const items = data.items || [];
                notify(`${items.length} résultat(s) trouvé(s).`, 'success');
            } catch (error) {
                notify(error.message || 'Recherche impossible.', 'error');
            }
        }

        const MOLCallUI = (() => {
            let current = {
                thread: null,
                type: 'audio',
                stream: null,
                startedAt: null,
                timer: null,
                muted: false,
                cameraOff: false,
                speakerOn: true,
                callId: null,
            };

            function callUrlsConfig() {
                return callUrls || window.MOL_CALL?.urls || {};
            }

            function ensureCallOverlay() {
                let overlay = document.getElementById('molPremiumCallOverlay');

                if (overlay) {
                    return overlay;
                }

                overlay = document.createElement('div');
                overlay.id = 'molPremiumCallOverlay';
                overlay.className = 'mol-premium-call-overlay is-hidden';
                overlay.innerHTML = `
                    <div class="mol-premium-call-backdrop" data-call-close></div>

                    <section class="mol-premium-call-shell" role="dialog" aria-modal="true" aria-label="Appel">
                        <header class="mol-premium-call-header">
                            <div class="mol-premium-call-user">
                                <span class="mol-premium-call-avatar-wrap">
                                    <img id="molPremiumCallAvatar" class="mol-premium-call-avatar" src="${escapeHtml(defaultAvatar())}" alt="">
                                    <span class="mol-premium-call-live-dot"></span>
                                </span>

                                <span class="mol-premium-call-user-text">
                                    <strong id="molPremiumCallName">Contact</strong>
                                    <small id="molPremiumCallHeaderStatus">Connexion en cours...</small>
                                </span>
                            </div>

                            <div class="mol-premium-call-header-actions">
                                <span class="mol-premium-call-chip" id="molPremiumCallTypeChip">
                                    <i class="bi bi-telephone-fill"></i>
                                    <span>Appel audio</span>
                                </span>

                                <button type="button" class="mol-premium-call-top-btn" data-call-close aria-label="Fermer">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </header>

                        <main class="mol-premium-call-main">
                            <aside class="mol-premium-call-side">
                                <div class="mol-premium-call-identity">
                                    <div class="mol-premium-call-rings">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                        <img id="molPremiumCallBigAvatar" src="${escapeHtml(defaultAvatar())}" alt="">
                                    </div>

                                    <h3 id="molPremiumCallTitle">Appel</h3>
                                    <p id="molPremiumCallStatus">Préparation de l’appel...</p>
                                    <div id="molPremiumCallDuration" class="mol-premium-call-duration">00:00</div>
                                </div>

                                <div class="mol-premium-call-info-grid">
                                    <div>
                                        <i class="bi bi-shield-check"></i>
                                        <strong>Sécurisé</strong>
                                        <span>Connexion privée</span>
                                    </div>

                                    <div>
                                        <i class="bi bi-lightning-charge"></i>
                                        <strong>Premium</strong>
                                        <span>Interface fluide</span>
                                    </div>
                                </div>
                            </aside>

                            <section class="mol-premium-call-stage">
                                <div class="mol-premium-call-empty" id="molPremiumCallEmpty">
                                    <i class="bi bi-camera-video-fill"></i>
                                    <span>Vidéo en attente</span>
                                </div>

                                <video id="molPremiumRemoteVideo" class="mol-premium-call-remote" autoplay playsinline></video>

                                <div class="mol-premium-call-local-card" id="molPremiumLocalCard">
                                    <video id="molPremiumLocalVideo" autoplay muted playsinline></video>
                                    <span>Vous</span>
                                </div>
                            </section>
                        </main>

                        <footer class="mol-chat-thread-composer mol-messenger-footer">
                            <input type="file" data-thread-file hidden>
                            <input type="file" data-thread-camera-file accept="image/*" capture="environment" hidden>

                            <div class="mol-messenger-footer__row">
                                <button type="button" class="mol-messenger-footer__chevron" data-thread-tools-toggle title="Plus">
                                    <i class="bi bi-chevron-right"></i>
                                </button>

                                <div class="mol-messenger-footer__tools" data-thread-tools hidden>
                                    <button type="button" class="mol-messenger-footer__tool" data-thread-camera title="Caméra">
                                        <i class="bi bi-camera-fill"></i>
                                    </button>

                                    <button type="button" class="mol-messenger-footer__tool" data-thread-attach title="Fichier">
                                        <i class="bi bi-paperclip"></i>
                                    </button>

                                    <button type="button" class="mol-messenger-footer__tool" data-thread-voice title="Vocal">
                                        <i class="bi bi-mic-fill"></i>
                                    </button>
                                </div>

                                <div class="mol-messenger-footer__input-wrap">
                                    <textarea
                                        class="mol-chat-thread-textarea mol-messenger-footer__input"
                                        rows="1"
                                        placeholder="Message"
                                        data-thread-input
                                    ></textarea>

                                    <button type="button" class="mol-messenger-footer__emoji" data-thread-emoji title="Emoji">
                                        <i class="bi bi-emoji-smile-fill"></i>
                                    </button>
                                </div>

                                <button type="button" class="mol-messenger-footer__like" data-thread-like title="J’aime">
                                    <i class="bi bi-hand-thumbs-up-fill"></i>
                                </button>

                                <button type="button" class="mol-messenger-footer__send" data-thread-send title="Envoyer" hidden>
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                        </footer>
                    </section>
                `;

                document.body.appendChild(overlay);
                bindCallOverlay(overlay);

                return overlay;
            }

            function bindCallOverlay(overlay) {
                overlay.addEventListener('click', (event) => {
                    if (event.target.closest('[data-call-close]')) {
                        event.preventDefault();
                        close();
                        return;
                    }

                    if (event.target.closest('[data-call-toggle-mic]')) {
                        event.preventDefault();
                        toggleMic();
                        return;
                    }

                    if (event.target.closest('[data-call-toggle-camera]')) {
                        event.preventDefault();
                        toggleCamera();
                        return;
                    }

                    if (event.target.closest('[data-call-toggle-speaker]')) {
                        event.preventDefault();
                        toggleSpeaker();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !overlay.classList.contains('is-hidden')) {
                        close();
                    }
                });
            }

            function qsCall(id) {
                return document.getElementById(id);
            }

            function threadName(thread) {
                return thread?.item?.name || 'Contact';
            }

            function threadAvatar(thread) {
                return normalizeAvatar(thread?.item?.avatarUrl || thread?.item?.avatar || defaultAvatar());
            }

            function setCallText(type, thread) {
                const isVideo = type === 'video';
                const name = threadName(thread);
                const avatar = threadAvatar(thread);

                const chip = qsCall('molPremiumCallTypeChip');
                const title = qsCall('molPremiumCallTitle');

                if (qsCall('molPremiumCallName')) qsCall('molPremiumCallName').textContent = name;
                if (qsCall('molPremiumCallTitle')) qsCall('molPremiumCallTitle').textContent = name;
                if (qsCall('molPremiumCallAvatar')) qsCall('molPremiumCallAvatar').src = avatar;
                if (qsCall('molPremiumCallBigAvatar')) qsCall('molPremiumCallBigAvatar').src = avatar;

                if (qsCall('molPremiumCallStatus')) {
                    qsCall('molPremiumCallStatus').textContent = isVideo
                        ? 'Appel vidéo en cours...'
                        : 'Appel audio en cours...';
                }

                if (qsCall('molPremiumCallHeaderStatus')) {
                    qsCall('molPremiumCallHeaderStatus').textContent = 'Sonnerie...';
                }

                if (chip) {
                    chip.innerHTML = isVideo
                        ? '<i class="bi bi-camera-video-fill"></i><span>Appel vidéo</span>'
                        : '<i class="bi bi-telephone-fill"></i><span>Appel audio</span>';
                }

                if (title) {
                    title.dataset.callType = type;
                }
            }

            async function open(thread, type = 'audio', options = {}) {
                const overlay = ensureCallOverlay();
                const urlsConfig = callUrlsConfig();
                const isVideo = type === 'video';
                const isIncoming = options.incoming === true;

                current.thread = thread;
                current.type = type;
                current.startedAt = Date.now();
                current.muted = false;
                current.cameraOff = false;
                current.speakerOn = true;
                current.callId = options.callId || null;

                setCallText(type, thread);

                overlay.classList.remove('is-hidden');
                overlay.classList.add('is-open');
                document.body.classList.add('mol-premium-call-open');

                qsCall('molPremiumLocalCard')?.classList.toggle('is-audio-only', !isVideo);
                qsCall('molPremiumCallEmpty')?.classList.toggle('is-audio-only', !isVideo);

                updateToolStates();
                startDurationTimer();

                try {
                    await startMedia(type);
                } catch (error) {
                    console.error('[MOL CALL MEDIA]', error);

                    if (type === 'video') {
                        notify('Impossible d’ouvrir la caméra ou le micro.', 'error');
                    } else {
                        notify('Impossible d’ouvrir le micro.', 'error');
                    }
                }

                /*
                * CAS 1 : appel entrant accepté.
                * Important : on NE relance PAS callUrls.start,
                * sinon le destinataire recrée un nouvel appel au lieu d’accepter l’appel existant.
                */
                if (isIncoming) {
                    if (qsCall('molPremiumCallStatus')) {
                        qsCall('molPremiumCallStatus').textContent = 'Appel accepté';
                    }

                    if (qsCall('molPremiumCallHeaderStatus')) {
                        qsCall('molPremiumCallHeaderStatus').textContent = 'Connecté';
                    }

                    if (qsCall('molPremiumCallDuration')) {
                        qsCall('molPremiumCallDuration').textContent = '00:00';
                    }

                    window.dispatchEvent(new CustomEvent('mol:chat:call-accepted', {
                        detail: {
                            callId: current.callId,
                            conversationId: thread.id,
                            type,
                            thread,
                        }
                    }));

                    return;
                }

                /*
                * CAS 2 : appel sortant.
                * Ici seulement, on crée une nouvelle CallSession côté serveur.
                */
                if (urlsConfig.start) {
                    try {
                        const data = await fetchJson(tpl(urlsConfig.start, thread.id), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ type }),
                        });

                        current.callId = data.callId || data.id || data.call?.id || null;

                        if (qsCall('molPremiumCallStatus')) {
                            qsCall('molPremiumCallStatus').textContent = 'En attente de réponse...';
                        }

                        if (qsCall('molPremiumCallHeaderStatus')) {
                            qsCall('molPremiumCallHeaderStatus').textContent = 'En attente...';
                        }

                        window.dispatchEvent(new CustomEvent('mol:chat:call-started', {
                            detail: {
                                ...data,
                                callId: current.callId,
                                conversationId: thread.id,
                                type,
                                thread,
                            }
                        }));
                    } catch (error) {
                        console.error('[MOL CALL START]', error);

                        notify(error.message || 'Appel impossible.', 'error');

                        await close();
                    }

                    return;
                }

                /*
                * CAS 3 : aucune route serveur.
                * L’interface s’ouvre quand même, mais uniquement en mode local.
                */
                if (qsCall('molPremiumCallStatus')) {
                    qsCall('molPremiumCallStatus').textContent = 'Mode interface locale';
                }

                if (qsCall('molPremiumCallHeaderStatus')) {
                    qsCall('molPremiumCallHeaderStatus').textContent = 'Aucune route serveur configurée';
                }

                window.dispatchEvent(new CustomEvent('mol:chat:call-local-only', {
                    detail: {
                        conversationId: thread.id,
                        type,
                        thread,
                    }
                }));
            }

            async function startMedia(type) {
                stopMedia();

                const isVideo = type === 'video';

                if (!navigator.mediaDevices?.getUserMedia) {
                    throw new Error('getUserMedia non supporté');
                }

                current.stream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: isVideo ? {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
                    } : false
                });

                const localVideo = qsCall('molPremiumLocalVideo');

                if (localVideo) {
                    localVideo.srcObject = current.stream;
                }

                if (!isVideo) {
                    qsCall('molPremiumLocalCard')?.classList.add('is-audio-only');
                }
            }

            function stopMedia() {
                if (current.stream) {
                    current.stream.getTracks().forEach((track) => track.stop());
                    current.stream = null;
                }

                const localVideo = qsCall('molPremiumLocalVideo');
                const remoteVideo = qsCall('molPremiumRemoteVideo');

                if (localVideo) localVideo.srcObject = null;
                if (remoteVideo) remoteVideo.srcObject = null;
            }

            function toggleMic() {
                current.muted = !current.muted;

                current.stream?.getAudioTracks()?.forEach((track) => {
                    track.enabled = !current.muted;
                });

                updateToolStates();
            }

            function toggleCamera() {
                if (current.type !== 'video') return;

                current.cameraOff = !current.cameraOff;

                current.stream?.getVideoTracks()?.forEach((track) => {
                    track.enabled = !current.cameraOff;
                });

                updateToolStates();
            }

            function toggleSpeaker() {
                current.speakerOn = !current.speakerOn;
                updateToolStates();
            }

            function updateToolStates() {
                const mic = document.querySelector('[data-call-toggle-mic]');
                const cam = document.querySelector('[data-call-toggle-camera]');
                const speaker = document.querySelector('[data-call-toggle-speaker]');

                if (mic) {
                    mic.classList.toggle('is-danger', current.muted);
                    mic.innerHTML = current.muted
                        ? '<i class="bi bi-mic-mute-fill"></i>'
                        : '<i class="bi bi-mic-fill"></i>';
                }

                if (cam) {
                    cam.disabled = current.type !== 'video';
                    cam.classList.toggle('is-danger', current.cameraOff);
                    cam.innerHTML = current.cameraOff
                        ? '<i class="bi bi-camera-video-off-fill"></i>'
                        : '<i class="bi bi-camera-video-fill"></i>';
                }

                if (speaker) {
                    speaker.classList.toggle('is-active', current.speakerOn);
                    speaker.innerHTML = current.speakerOn
                        ? '<i class="bi bi-volume-up-fill"></i>'
                        : '<i class="bi bi-volume-mute-fill"></i>';
                }
            }

            function startDurationTimer() {
                clearInterval(current.timer);

                const duration = qsCall('molPremiumCallDuration');

                current.timer = setInterval(() => {
                    if (!duration || !current.startedAt) return;

                    const total = Math.floor((Date.now() - current.startedAt) / 1000);
                    const minutes = String(Math.floor(total / 60)).padStart(2, '0');
                    const seconds = String(total % 60).padStart(2, '0');

                    duration.textContent = `${minutes}:${seconds}`;
                }, 1000);
            }

            async function close() {
                const overlay = ensureCallOverlay();
                const urlsConfig = callUrlsConfig();

                clearInterval(current.timer);

                if (current.callId && urlsConfig.end) {
                    try {
                        await fetchJson(tpl(urlsConfig.end, current.callId), {
                            method: 'POST',
                        });
                    } catch (_) {}
                }

                stopMedia();

                overlay.classList.remove('is-open');
                overlay.classList.add('is-hidden');
                document.body.classList.remove('mol-premium-call-open');

                current.thread = null;
                current.callId = null;
            }

            return {
                open,
                close,
            };
        })();

        window.MOLCallUI = MOLCallUI;

        const MOLIncomingCallUI = (() => {
            let currentIncoming = null;
            let pollTimer = null;
            let lastCallId = null;
            let ringingAudio = null;
            let eventsBound = false;

            function callUrlsConfig() {
                return {
                    ...(window.MOL_CALL?.urls || {}),
                    ...(window.MOL_CHAT?.call || {}),
                    ...(callUrls || {})
                };
            }

            function ensureIncomingOverlay() {
                let overlay = document.getElementById('molIncomingCallOverlay');

                if (overlay) {
                    bindIncomingEvents();
                    return overlay;
                }

                overlay = document.createElement('div');
                overlay.id = 'molIncomingCallOverlay';
                overlay.className = 'mol-incoming-call-overlay is-hidden';

                overlay.innerHTML = `
                    <div class="mol-incoming-call-backdrop"></div>

                    <section class="mol-incoming-call-card" role="dialog" aria-modal="true">
                        <div class="mol-incoming-call-ring">
                            <span></span>
                            <span></span>
                            <span></span>
                            <img id="molIncomingCallerAvatar" src="${escapeHtml(defaultAvatar())}" alt="">
                        </div>

                        <div class="mol-incoming-call-content">
                            <div class="mol-incoming-call-label" id="molIncomingCallType">
                                Appel entrant
                            </div>

                            <h3 id="molIncomingCallerName">Contact</h3>

                            <p id="molIncomingCallText">
                                souhaite vous appeler.
                            </p>
                        </div>

                        <div class="mol-incoming-call-actions">
                            <button
                                type="button"
                                class="mol-incoming-call-btn mol-incoming-call-btn--decline"
                                data-incoming-decline
                            >
                                <i class="bi bi-telephone-x-fill"></i>
                                <span>Refuser</span>
                            </button>

                            <button
                                type="button"
                                class="mol-incoming-call-btn mol-incoming-call-btn--accept"
                                data-incoming-accept
                            >
                                <i class="bi bi-telephone-fill"></i>
                                <span>Accepter</span>
                            </button>
                        </div>
                    </section>
                `;

                document.body.appendChild(overlay);
                bindIncomingEvents();

                return overlay;
            }

            function bindIncomingEvents() {
                if (eventsBound) return;

                eventsBound = true;

                document.addEventListener('click', async (event) => {
                    const acceptBtn = event.target.closest('[data-incoming-accept]');
                    const declineBtn = event.target.closest('[data-incoming-decline]');

                    if (acceptBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();

                        await acceptIncoming(acceptBtn);
                        return;
                    }

                    if (declineBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();

                        await declineIncoming(declineBtn);
                    }
                }, true);
            }

            function setButtonLoading(button, loading, text = 'Traitement...') {
                if (!button) return;

                if (loading) {
                    button.dataset.oldHtml = button.innerHTML;
                    button.disabled = true;
                    button.classList.add('is-loading');
                    button.innerHTML = `
                        <span class="spinner-border spinner-border-sm"></span>
                        <span>${escapeHtml(text)}</span>
                    `;
                    return;
                }

                button.disabled = false;
                button.classList.remove('is-loading');

                if (button.dataset.oldHtml) {
                    button.innerHTML = button.dataset.oldHtml;
                    delete button.dataset.oldHtml;
                }
            }

            function openIncoming(call) {
                if (!call || !call.callId) return;

                if (lastCallId && Number(lastCallId) === Number(call.callId)) {
                    return;
                }

                lastCallId = Number(call.callId);
                currentIncoming = call;

                const overlay = ensureIncomingOverlay();

                const typeLabel = call.type === 'video'
                    ? 'Appel vidéo entrant'
                    : 'Appel audio entrant';

                const avatarUrl = normalizeAvatar(call.callerAvatar || defaultAvatar());

                const avatarEl = document.getElementById('molIncomingCallerAvatar');
                const nameEl = document.getElementById('molIncomingCallerName');
                const typeEl = document.getElementById('molIncomingCallType');
                const textEl = document.getElementById('molIncomingCallText');

                if (avatarEl) avatarEl.src = avatarUrl;
                if (nameEl) nameEl.textContent = call.callerName || 'Contact';
                if (typeEl) typeEl.textContent = typeLabel;

                if (textEl) {
                    textEl.textContent = call.type === 'video'
                        ? 'souhaite démarrer une visio avec vous.'
                        : 'souhaite démarrer un appel vocal avec vous.';
                }

                overlay.classList.remove('is-hidden');
                overlay.classList.add('is-open');

                playRing();
            }

            function closeIncoming() {
                const overlay = document.getElementById('molIncomingCallOverlay');

                if (overlay) {
                    overlay.classList.remove('is-open');
                    overlay.classList.add('is-hidden');
                }

                stopRing();
                currentIncoming = null;
            }

            function playRing() {
                try {
                    if (ringingAudio) {
                        ringingAudio.pause();
                        ringingAudio.currentTime = 0;
                    }

                    ringingAudio = new Audio(buildUrl('/sounds/call-ring.mp3'));
                    ringingAudio.loop = true;
                    ringingAudio.volume = 0.42;
                    ringingAudio.play().catch(() => {});
                } catch (_) {}
            }

            function stopRing() {
                try {
                    if (ringingAudio) {
                        ringingAudio.pause();
                        ringingAudio.currentTime = 0;
                    }
                } catch (_) {}
            }

            async function acceptIncoming(button = null) {
                const call = currentIncoming;

                if (!call || !call.callId) {
                    notify('Aucun appel entrant à accepter.', 'error');
                    return;
                }

                const urlsConfig = callUrlsConfig();

                setButtonLoading(button, true, 'Connexion...');

                try {
                    if (!urlsConfig.accept) {
                        throw new Error('Route accept manquante dans window.MOL_CHAT.call.accept.');
                    }

                    const acceptUrl = tpl(urlsConfig.accept, call.callId);

                    console.log('[MOL ACCEPT] call =', call);
                    console.log('[MOL ACCEPT] acceptUrl =', buildUrl(acceptUrl));

                    const response = await fetch(buildUrl(acceptUrl), {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const raw = await response.text();
                    let data = {};

                    try {
                        data = raw ? JSON.parse(raw) : {};
                    } catch (_) {
                        data = { raw };
                    }

                    console.log('[MOL ACCEPT] status =', response.status);
                    console.log('[MOL ACCEPT] response =', data);

                    if (!response.ok || data.ok === false || data.success === false) {
                        throw new Error(data.message || data.error || data.raw || `HTTP ${response.status}`);
                    }

                    closeIncoming();

                    if (!window.MOLCallUI && typeof MOLCallUI === 'undefined') {
                        throw new Error('MOLCallUI introuvable. Ajoute window.MOLCallUI = MOLCallUI après le module MOLCallUI.');
                    }

                    const callThread = {
                        kind: 'private',
                        id: call.conversationId || call.callId,
                        item: {
                            name: call.callerName || 'Contact',
                            avatar: call.callerAvatar || defaultAvatar(),
                            avatarUrl: call.callerAvatar || defaultAvatar(),
                        }
                    };

                    const ui = window.MOLCallUI || MOLCallUI;

                    await ui.open(callThread, call.type || 'audio', {
                        incoming: true,
                        callId: call.callId
                    });

                    notify('Appel accepté.', 'success');
                } catch (error) {
                    console.error('[MOL ACCEPT ERROR]', error);

                    notify(
                        error.message || 'Impossible d’accepter l’appel.',
                        'error'
                    );
                } finally {
                    setButtonLoading(button, false);
                }
            }

            async function declineIncoming(button = null) {
                const call = currentIncoming;

                if (!call || !call.callId) {
                    closeIncoming();
                    return;
                }

                const urlsConfig = callUrlsConfig();

                setButtonLoading(button, true, 'Refus...');

                try {
                    if (urlsConfig.decline) {
                        await fetchJson(tpl(urlsConfig.decline, call.callId), {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                    }
                } catch (error) {
                    console.warn('[MOL INCOMING DECLINE ERROR]', error);
                } finally {
                    setButtonLoading(button, false);
                    closeIncoming();
                }
            }

            async function checkIncoming() {
                const urlsConfig = callUrlsConfig();
                const incomingUrl = urlsConfig.incoming || '/chat/calls/incoming';

                try {
                    const url = incomingUrl + (String(incomingUrl).includes('?') ? '&' : '?') + '_ts=' + Date.now();
                    const data = await fetchJson(url);

                    if (!data.incoming) {
                        lastCallId = null;
                        return;
                    }

                    const call = data.incoming;

                    if (call.startedAt) {
                        const startedAt = new Date(call.startedAt).getTime();
                        const ageMs = Date.now() - startedAt;

                        if (Number.isFinite(ageMs) && ageMs > 45000) {
                            return;
                        }
                    }

                    const meId = Number(window.MOL_CHAT?.meId || window.MOL_CALL?.meId || 0);
                    const callerId = Number(call.callerId || 0);

                    if (meId && callerId && meId === callerId) {
                        return;
                    }

                    if (call.status === 'ringing') {
                        openIncoming(call);
                    }
                } catch (error) {
                    console.error('[MOL INCOMING CALL]', error);
                }
            }

            function startPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                }

                console.log('[MOL INCOMING CALL] polling démarré');

                checkIncoming();

                pollTimer = setInterval(() => {
                    checkIncoming();
                }, 2500);
            }

            return {
                startPolling,
                checkIncoming,
                openIncoming,
                closeIncoming,
                acceptIncoming,
                declineIncoming,
            };
        })();



        window.MOLIncomingCallUI = MOLIncomingCallUI;

        async function startCall(thread, type) {
            if (!thread || thread.kind !== 'private') {
                notify('Les appels de groupe ne sont pas activés pour le moment.', 'error');
                return;
            }

            await MOLCallUI.open(thread, type);
        }

        function handleTyping(thread) {
            if (!urls.typing || !thread || thread.kind !== 'private') return;

            const id = thread.id;
            const key = `typing:${id}`;

            const alreadyTyping = state.typingTimers.has(key);

            clearTimeout(state.typingTimers.get(key));

            if (!alreadyTyping) {
                fetchJson(tpl(urls.typing, id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typing: true }),
                }).catch(() => {});
            }

            const timer = setTimeout(() => {
                state.typingTimers.delete(key);

                fetchJson(tpl(urls.typing, id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ typing: false }),
                }).catch(() => {});
            }, 1200);

            state.typingTimers.set(key, timer);
        }

        function showTyping(thread) {
            if (!thread?.el) return;

            state.typingVisible.add(thread.key);

            renderThreadMessages(thread);
            scrollThreadBottom(thread.el);

            clearTimeout(thread.typingHideTimer);

            thread.typingHideTimer = setTimeout(() => {
                hideTyping(thread);
            }, 2200);
        }

        function hideTyping(thread) {
            if (!thread?.el) return;

            state.typingVisible.delete(thread.key);
            renderThreadMessages(thread);

            clearTimeout(thread.typingHideTimer);
            thread.typingHideTimer = null;
        }

        function appendTyping(thread) {
            const box = $('[data-thread-messages]', thread.el);
            if (!box) return;

            const avatar = normalizeAvatar(thread.item?.avatarUrl || thread.item?.avatar || defaultAvatar());

            box.insertAdjacentHTML('beforeend', `
                <div class="mol-chat-typing-row">
                    <img
                        class="mol-chat-typing-avatar"
                        src="${escapeHtml(avatar)}"
                        alt=""
                        onerror="this.src='${escapeHtml(defaultAvatar())}'"
                    >

                    <div class="mol-chat-typing-bubble" aria-label="Écrit...">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            `);
        }

        function subscribeThread(thread) {
            if (!cfg.mercureHub || typeof EventSource === 'undefined') return;
            if (state.eventSources.has(thread.key)) return;

            try {
                const url = new URL(cfg.mercureHub);
                if (thread.kind === 'private') {
                    url.searchParams.append('topic', `/conversations/${thread.id}/messages`);
                    url.searchParams.append('topic', `/conversations/${thread.id}/status`);
                    url.searchParams.append('topic', `chat/conversation/${thread.id}/typing`);
                    url.searchParams.append('topic', `chat/conversation/${thread.id}`);
                } else {
                    url.searchParams.append('topic', `/groups/${thread.id}/messages`);
                    url.searchParams.append('topic', `chat/group/${thread.id}`);
                }

                const es = new EventSource(url.toString());
                es.onmessage = async event => {
                    let payload = {};
                    try { payload = JSON.parse(event.data || '{}'); } catch (_) { return; }

                    if (payload.typing !== undefined && toInt(payload.userId) !== toInt(cfg.meId)) {
                        payload.typing ? showTyping(thread) : hideTyping(thread);
                        return;
                    }

                    await loadThreadMessages(thread, true);
                    if (thread.kind === 'private') loadPrivateConversations(); else loadGroups();
                    updateUnreadBadge();
                };
                es.onerror = () => {};
                state.eventSources.set(thread.key, es);
            } catch (_) {}
        }

        async function updateUnreadBadge() {
            const badge = $('[data-mol-chat-unread]');
            if (!badge || !urls.unreadCount) return;
            try {
                const data = await fetchJson(urls.unreadCount + (String(urls.unreadCount).includes('?') ? '&' : '?') + '_ts=' + Date.now());
                const n = toInt(data.unreadCount || data.count || 0);
                badge.hidden = n <= 0;
                badge.textContent = n > 99 ? '99+' : String(n);
            } catch (_) {}
        }

        async function runPanelAction(action) {
            closeAllMenus();
            if (action === 'mark_all_read') {
                try {
                    await fetchJson(urls.markAllRead, { method: 'POST' });
                    notify('Toutes les conversations sont marquées comme lues.', 'success');
                    await loadPrivateConversations();
                    await updateUnreadBadge();
                } catch (error) {
                    notify(error.message || 'Action impossible.', 'error');
                }
                return;
            }

            if (action === 'toggle_notifications') {
                try {
                    const fd = new FormData();
                    fd.append('_token', cfg.csrf?.notification || '');
                    const data = await fetchJson(urls.notificationToggle, { method: 'POST', body: fd });
                    notify(data.message || 'Préférences de notifications mises à jour.', 'success');
                } catch (error) {
                    notify(error.message || 'Notifications indisponibles.', 'error');
                }
                return;
            }

            if (action === 'message_sounds') {
                const current = localStorage.getItem('mol_chat_sounds_enabled') !== '0';
                localStorage.setItem('mol_chat_sounds_enabled', current ? '0' : '1');
                window.MOL_CHAT_SOUND_ENABLED = !current;
                notify(current ? 'Sons désactivés' : 'Sons activés');
                return;
            }

            if (action === 'block_setting' || action === 'blockSettings') {
                if (window.MolBlockSettings && typeof window.MolBlockSettings.open === 'function') {
                    window.MolBlockSettings.open();
                }
                return;
            }

            if (action === 'groups') {
                switchTab('groups');
                const name = await askText({ title: 'Create a group chat', placeholder: 'Nom du groupe', confirm: 'Créer' });
                if (!name) return;
                try {
                    const fd = new FormData();
                    fd.append('name', name.trim());
                    fd.append('visibility', 'private');
                    const data = await fetchJson(groupUrls.create, { method: 'POST', body: fd });
                    notify(data.message || 'Groupe créé.', 'success');
                    await loadGroups();
                } catch (error) {
                    notify(error.message || 'Création impossible.', 'error');
                }
            }
        }

        async function runGlobalSearch() {
            const input = $('#molChatGlobalSearchInput');
            const results = $('#molChatGlobalResults');
            if (!input || !results) return;
            const q = input.value.trim();
            if (q.length < 2) {
                results.innerHTML = '<div class="mol-chat-empty">Tape au moins 2 caractères.</div>';
                return;
            }
            try {
                const url = new URL(buildUrl(urls.globalSearch));
                url.searchParams.set('q', q);
                const data = await fetchJson(url.toString());
                const items = data.items || [];
                if (!items.length) {
                    results.innerHTML = '<div class="mol-chat-empty">Aucun message trouvé.</div>';
                    return;
                }
                results.innerHTML = items.map(item => `
                    <button type="button" class="mol-chat-global-result" data-search-open="${escapeHtml(item.kind || 'private')}" data-id="${toInt(item.conversationId || item.groupId || item.id)}">
                        <strong>${escapeHtml(item.authorName || item.name || 'Message')}</strong>
                        <span>${escapeHtml(item.content || item.attachmentName || 'Message')}</span>
                    </button>
                `).join('');
            } catch (error) {
                results.innerHTML = `<div class="mol-chat-empty">${escapeHtml(error.message || 'Recherche indisponible.')}</div>`;
            }
        }

        function closeAllMenus() {
            $$('.mol-chat-menu.is-open').forEach(menu => {
                menu.classList.remove('is-open');
                menu.classList.remove('is-dropup');
            });
        }


        function bindGlobalEvents() {
            fab.addEventListener('click', openPanel);
            $$('[data-mol-chat-open]').forEach(btn => btn.addEventListener('click', e => { e.preventDefault(); openPanel(); }));
            $$('[data-mol-chat-close]').forEach(btn => btn.addEventListener('click', e => { e.preventDefault(); closePanel(); }));
            $$('[data-mol-tab]').forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.molTab)));

            $('#molChatSearchInput')?.addEventListener('input', debounce(() => renderPrivateConversations(state.privateItems), 120));
            $('#molChatGlobalSearchInput')?.addEventListener('input', debounce(runGlobalSearch, 280));

            document.addEventListener('click', async event => {
                const menuToggle = event.target.closest('[data-mol-menu-toggle]');
                if (menuToggle) {
                    event.preventDefault();
                    event.stopPropagation();

                    const id = menuToggle.dataset.molMenuToggle;
                    const menu = document.getElementById(id);

                    if (!menu) return;

                    const wasOpen = menu.classList.contains('is-open');

                    closeAllMenus();

                    if (!wasOpen) {
                        menu.classList.add('is-open');

                        requestAnimationFrame(() => {
                            const rect = menu.getBoundingClientRect();

                            if (rect.bottom > window.innerHeight - 12) {
                                menu.classList.add('is-dropup');
                            } else {
                                menu.classList.remove('is-dropup');
                            }
                        });
                    }

                    return;
                }

                const panelAction = event.target.closest('[data-mol-panel-action]');
                if (panelAction) {
                    event.preventDefault();
                    await runPanelAction(panelAction.dataset.molPanelAction);
                    return;
                }

                const item = event.target.closest('[data-open-thread]');
                if (item) {
                    event.preventDefault();
                    await openThread(item.dataset.openThread, item.dataset.id);
                    return;
                }

                const searchOpen = event.target.closest('[data-search-open]');
                if (searchOpen) {
                    event.preventDefault();
                    await openThread(searchOpen.dataset.searchOpen, searchOpen.dataset.id);
                    return;
                }

                const threadAction = event.target.closest('[data-thread-action]');
                if (threadAction) {
                    event.preventDefault();
                    const threadEl = threadAction.closest('.mol-chat-thread');
                    const thread = state.threads.get(threadEl?.dataset.threadKey || '');
                    if (thread) await runThreadAction(thread, threadAction.dataset.threadAction);
                    return;
                }

                const messageAction = event.target.closest('[data-message-action]');
                if (messageAction) {
                    event.preventDefault();

                    const row = messageAction.closest('.mol-chat-message-row');
                    const threadEl = messageAction.closest('.mol-chat-thread');
                    const thread = state.threads.get(threadEl?.dataset.threadKey || '');

                    if (!row || !thread) return;

                    const messageId = toInt(row.dataset.messageId);
                    const action = messageAction.dataset.messageAction;

                    if (action === 'reply') prepareReply(thread, row);
                    if (action === 'react') await reactToMessage(thread, messageId, messageAction);
                    if (action === 'delete') await deleteMessage(thread, messageId);

                    return;
                }

                if (!event.target.closest('.mol-chat-menu')) {
                    closeAllMenus();
                }
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeAllMenus();
            });

            window.addEventListener('beforeunload', () => {
                if (presenceUrls.offline) {
                    try {
                        navigator.sendBeacon?.(buildUrl(presenceUrls.offline), new URLSearchParams());
                    } catch (_) {}
                }
            });
        }

        async function startWithUser(userId) {
            if (!userId || !urls.withUser) return;
            try {
                const data = await fetchJson(tpl(urls.withUser, userId), { method: 'POST' });
                openPanel();
                await loadPrivateConversations();
                const conversationId = data.conversationId || data.id;
                if (conversationId) await openThread('private', conversationId);
            } catch (error) {
                notify(error.message || 'Conversation impossible.', 'error');
            }
        }

        function startPolling() {
            if (state.listPoll) clearInterval(state.listPoll);
            if (state.unreadPoll) clearInterval(state.unreadPoll);

            state.listPoll = setInterval(() => {
                if (state.panelOpen && state.currentTab === 'private') loadPrivateConversations();
                state.threads.forEach(thread => {
                    if (!document.hidden) loadThreadMessages(thread, false);
                });
            }, cfg.mercureHub ? 25000 : 8000);

            state.unreadPoll = setInterval(updateUnreadBadge, 20000);
        }

        function startHeartbeat() {
            const beat = () => {
                if (presenceUrls.heartbeat) fetchJson(presenceUrls.heartbeat, { method: 'POST' }).catch(() => {});
            };
            beat();
            setInterval(beat, 30000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') beat();
            });
        }

        function boot() {
            bindGlobalEvents();
            startHeartbeat();
            startPolling();
            updateUnreadBadge();

            fixChatPanelPosition();

            window.addEventListener('resize', fixChatPanelPosition);

            window.addEventListener('load', fixChatPanelPosition);

            MOLIncomingCallUI.startPolling();

            window.MOLChatOpen = openPanel;
            window.MOLChatClose = closePanel;
            window.MOLChatRefreshList = loadPrivateConversations;
            window.MOLChatStartWith = startWithUser;
            window.MOLChatOpenConversation = id => openThread('private', id);
            window.MOLChatOpenGroup = id => openThread('group', id);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
})();

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.querySelector('[data-notification-toggle]');

        if (!button) {
            console.warn('Bouton notifications introuvable.');
            return;
        }

        const icon = button.querySelector('i');
        const label = button.querySelector('span');

        const toggleUrl = button.dataset.toggleUrl;
        const statusUrl = button.dataset.statusUrl;
        const csrfToken = button.dataset.csrf;

        function applyNotificationState(data) {
            if (!data || !data.success) {
                return;
            }

            label.textContent = data.enabled
                ? 'Désactiver les notifications'
                : 'Réactiver les notifications';

            icon.className = data.enabled
                ? 'bi bi-bell-slash'
                : 'bi bi-bell';

            button.dataset.enabled = data.enabled ? '1' : '0';
        }

        async function loadStatus() {
            try {
                const response = await fetch(statusUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                applyNotificationState(data);
            } catch (error) {
                console.error('Erreur chargement statut notifications :', error);
            }
        }

        button.addEventListener('click', async () => {
            button.disabled = true;

            const formData = new FormData();
            formData.append('_token', csrfToken);

            try {
                const response = await fetch(toggleUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    alert(data.message || 'Impossible de modifier les notifications.');
                    return;
                }

                applyNotificationState(data);

                console.log(data.message);
            } catch (error) {
                console.error('Erreur toggle notifications :', error);
                alert('Erreur JavaScript pendant la modification des notifications.');
            } finally {
                button.disabled = false;
            }
        });

        loadStatus();
    });

    async function actionBlockSettings() {
        closeMenu?.();

        if (window.MolBlockSettings && typeof window.MolBlockSettings.open === 'function') {
            window.MolBlockSettings.open();
            return;
        }

        const modal = document.getElementById('molBlockSettingsModal');

        if (modal && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
            modal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            return;
        }

        notify('error', 'Modale introuvable', 'Ajoute {% include "chat/_block_settings_modal.html.twig" %}.');
    }


    (function () {
    function initChatColombe() {
        const colombe = document.querySelector(
            '.mol-chat-colombe-img'
        );

        if (!colombe) {
            return;
        }

        window.MOL_CHAT = window.MOL_CHAT || {};

        if (window.MOL_CHAT.colombeTimer) {
            window.clearInterval(
                window.MOL_CHAT.colombeTimer
            );
        }

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        if (reducedMotion) {
            return;
        }

        function flapWings() {
            colombe.classList.remove('is-flapping');

            void colombe.offsetWidth;

            colombe.classList.add('is-flapping');

            window.setTimeout(function () {
                colombe.classList.remove('is-flapping');
            }, 650);
        }

        window.MOL_CHAT.colombeTimer =
            window.setInterval(flapWings, 8000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initChatColombe
        );
    } else {
        initChatColombe();
    }

    document.addEventListener(
        'turbo:load',
        initChatColombe
    );
})();


(function () {
    const MAX_DISPLAYS = 3;
    const FIRST_DISPLAY_DELAY = 5000;
    const DISPLAY_DURATION = 6500;
    const NEXT_DISPLAY_DELAY = 30000;

    function initColombeMessage() {
        const fab = document.getElementById('molChatFab');
        const panel = document.getElementById('molChatPanel');
        const label = fab?.querySelector('.mol-chat-fab-label');

        if (!fab || !panel || !label) {
            return;
        }

        window.MOL_CHAT = window.MOL_CHAT || {};

        /*
         * Nettoyage nécessaire avec Turbo pour éviter plusieurs timers
         * et plusieurs événements après les changements de page.
         */
        if (
            typeof window.MOL_CHAT.destroyColombeMessage ===
            'function'
        ) {
            window.MOL_CHAT.destroyColombeMessage();
        }

        const userId =
            fab.dataset.molChatUser || 'current-user';

        const storageKey =
            `mol-chat-colombe-displays-${userId}-v1`;

        let displayTimer = null;
        let hideTimer = null;
        let hiddenTimer = null;

        function getDisplayCount() {
            try {
                const value = Number.parseInt(
                    window.sessionStorage.getItem(storageKey),
                    10
                );

                return Number.isFinite(value) ? value : 0;
            } catch (error) {
                return 0;
            }
        }

        function setDisplayCount(count) {
            try {
                window.sessionStorage.setItem(
                    storageKey,
                    String(count)
                );
            } catch (error) {
                // Le stockage peut être désactivé par le navigateur.
            }
        }

        function isPanelOpen() {
            return (
                panel.getAttribute('aria-hidden') === 'false' ||
                panel.classList.contains('is-open') ||
                panel.classList.contains('show')
            );
        }

        function clearTimers() {
            window.clearTimeout(displayTimer);
            window.clearTimeout(hideTimer);
            window.clearTimeout(hiddenTimer);

            displayTimer = null;
            hideTimer = null;
            hiddenTimer = null;
        }

        function hideMessage() {
            window.clearTimeout(hideTimer);
            window.clearTimeout(hiddenTimer);

            label.classList.remove('is-colombe-visible');

            hiddenTimer = window.setTimeout(function () {
                if (
                    !label.classList.contains(
                        'is-colombe-visible'
                    )
                ) {
                    label.hidden = true;
                }
            }, 260);
        }

        function scheduleMessage(delay) {
            window.clearTimeout(displayTimer);

            if (
                isPanelOpen() ||
                getDisplayCount() >= MAX_DISPLAYS
            ) {
                return;
            }

            displayTimer = window.setTimeout(
                showMessage,
                delay
            );
        }

        function showMessage() {
            if (isPanelOpen()) {
                hideMessage();
                return;
            }

            const currentCount = getDisplayCount();

            if (currentCount >= MAX_DISPLAYS) {
                hideMessage();
                return;
            }

            label.hidden = false;

            window.requestAnimationFrame(function () {
                label.classList.add(
                    'is-colombe-visible'
                );
            });

            /*
             * Une apparition est immédiatement comptabilisée.
             * Elle ne reviendra plus après la troisième.
             */
            setDisplayCount(currentCount + 1);

            hideTimer = window.setTimeout(function () {
                hideMessage();

                if (getDisplayCount() < MAX_DISPLAYS) {
                    scheduleMessage(NEXT_DISPLAY_DELAY);
                }
            }, DISPLAY_DURATION);
        }

        function synchronizePanelState() {
            const opened = isPanelOpen();

            fab.classList.toggle(
                'is-hidden-by-panel',
                opened
            );

            if (opened) {
                clearTimers();
                hideMessage();
                return;
            }

            if (
                getDisplayCount() < MAX_DISPLAYS &&
                !label.classList.contains(
                    'is-colombe-visible'
                )
            ) {
                scheduleMessage(FIRST_DISPLAY_DELAY);
            }
        }

        const panelObserver = new MutationObserver(
            synchronizePanelState
        );

        panelObserver.observe(panel, {
            attributes: true,
            attributeFilter: [
                'aria-hidden',
                'class',
                'hidden'
            ]
        });

        function handleChatClick(event) {
            if (
                event.target.closest('[data-mol-chat-open]') ||
                event.target.closest('[data-mol-chat-close]')
            ) {
                /*
                 * Laisser d'abord le script du tchat modifier
                 * l'état du panneau.
                 */
                window.setTimeout(
                    synchronizePanelState,
                    50
                );
            }
        }

        document.addEventListener(
            'click',
            handleChatClick
        );

        window.MOL_CHAT.destroyColombeMessage =
            function () {
                clearTimers();
                panelObserver.disconnect();

                document.removeEventListener(
                    'click',
                    handleChatClick
                );
            };

        label.hidden = true;
        synchronizePanelState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initColombeMessage
        );
    } else {
        initColombeMessage();
    }

    document.addEventListener(
        'turbo:load',
        initColombeMessage
    );
})();



document.addEventListener('click', function (event) {
    const logoutLink = event.target.closest(
        '[data-mol-chat-reset-session]'
    );

    if (!logoutLink) {
        return;
    }

    const fab = document.getElementById('molChatFab');
    const userId =
        fab?.dataset.molChatUser || 'current-user';

    window.sessionStorage.removeItem(
        `mol-chat-colombe-displays-${userId}-v1`
    );
});