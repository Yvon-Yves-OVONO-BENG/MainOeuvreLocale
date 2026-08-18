(() => {
    'use strict';

    const root = document.querySelector('[data-admin-dashboard]');
    if (!root) return;

    const dataNode = document.getElementById('adminDashboardData');
    let dashboardData = {};

    try {
        dashboardData = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
    } catch (error) {
        console.error('Admin dashboard: données JSON invalides.', error);
    }

    const chartData = dashboardData.charts || {};
    const i18n = dashboardData.i18n || {};
    const charts = new Map();
    const loadingElements = new Set();

    const toNumber = (value) => {
        const number = Number(value);
        return Number.isFinite(number) ? number : 0;
    };

    /* =====================================================
       SPINNER GLOBAL — tous les éléments cliquables
       ===================================================== */
    const setLoading = (element, active = true) => {
        if (!(element instanceof HTMLElement)) return;

        if (active) {
            if (element.classList.contains('admin-is-loading')) return;
            element.classList.add('admin-is-loading');
            element.setAttribute('aria-busy', 'true');
            loadingElements.add(element);
            return;
        }

        element.classList.remove('admin-is-loading');
        element.removeAttribute('aria-busy');
        loadingElements.delete(element);
    };

    const resetLoaders = () => {
        loadingElements.forEach((element) => setLoading(element, false));
        root.querySelectorAll('.admin-is-loading').forEach((element) => setLoading(element, false));
    };

    const isTemporaryControl = (element) => Boolean(
        element.matches([
            '[data-admin-tab]',
            '[data-growth-range]',
            '[data-chat-open]',
            '[data-open-admin-tab]',
            '[data-soon-label]',
            '#adminSidebarToggle',
            '#adminOpenCommand',
            '#adminCloseCommand'
        ].join(',')) ||
        element.closest('#adminCommandModal') && !(element instanceof HTMLAnchorElement)
    );

    root.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const element = event.target.closest('a, button, [role="button"]');
        if (!(element instanceof HTMLElement)) return;
        if (element.matches('[disabled], [aria-disabled="true"]')) return;
        if (event.defaultPrevented) return;

        setLoading(element, true);

        window.setTimeout(
            () => setLoading(element, false),
            isTemporaryControl(element) ? 420 : 10000
        );
    }, true);

    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const submitter = event.submitter || form.querySelector('[type="submit"]');
        if (submitter instanceof HTMLElement) {
            setLoading(submitter, true);
            window.setTimeout(() => setLoading(submitter, false), 10000);
        }
    }, true);

    window.addEventListener('pageshow', resetLoaders);
    document.addEventListener('turbo:load', resetLoaders);
    document.addEventListener('turbo:render', resetLoaders);

    /* =====================================================
       TOASTS
       ===================================================== */
    const toastWrap = document.getElementById('adminToastWrap');

    const showToast = (message, type = '') => {
        if (!toastWrap) return;

        const toast = document.createElement('div');
        toast.className = `admin-toast${type ? ` admin-toast--${type}` : ''}`;
        toast.textContent = message;
        toastWrap.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 220);
        }, 2600);
    };

    root.querySelectorAll('.admin-toast').forEach((toast, index) => {
        window.setTimeout(() => toast.classList.add('is-visible'), 100 + index * 100);
        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 220);
        }, 3200 + index * 140);
    });

    root.querySelectorAll('[data-soon-label]').forEach((button) => {
        button.addEventListener('click', () => {
            const label = button.getAttribute('data-soon-label') || 'Cette fonctionnalité';
            showToast(`${label} : ${i18n.comingSoon || 'bientôt disponible'}`);
        });
    });

    /* =====================================================
       SIDEBAR RESPONSIVE
       ===================================================== */
    const sidebarToggle = document.getElementById('adminSidebarToggle');
    const sidebarBody = document.getElementById('adminSidebarBody');

    sidebarToggle?.addEventListener('click', () => {
        if (!sidebarBody) return;
        const opened = sidebarBody.classList.toggle('is-open');
        sidebarToggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });

    sidebarBody?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth > 900) return;
            sidebarBody.classList.remove('is-open');
            sidebarToggle?.setAttribute('aria-expanded', 'false');
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth <= 900 || !sidebarBody) return;
        sidebarBody.classList.remove('is-open');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    });

    /* =====================================================
       HORLOGE ET COMPTEURS
       ===================================================== */
    const liveClock = document.getElementById('adminLiveClock');

    const updateClock = () => {
        if (!liveClock) return;
        liveClock.textContent = new Intl.DateTimeFormat('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        }).format(new Date());
    };

    updateClock();
    window.setInterval(updateClock, 1000);

    root.querySelectorAll('[data-count]').forEach((element) => {
        const target = toNumber(String(element.getAttribute('data-count') || '0').replace(/\s+/g, ''));
        const startedAt = performance.now();
        const duration = 720;

        const animate = (now) => {
            const progress = Math.min((now - startedAt) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = new Intl.NumberFormat('fr-FR').format(Math.floor(target * eased));
            if (progress < 1) requestAnimationFrame(animate);
        };

        requestAnimationFrame(animate);
    });

    /* =====================================================
       CHAT
       ===================================================== */
    root.querySelectorAll('[data-chat-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (typeof window.MOLChatOpen === 'function') {
                window.MOLChatOpen();
            } else {
                showToast('Le module de messages n’est pas disponible sur cette page.', 'warning');
            }
        });
    });

    /* =====================================================
       COMMAND CENTER
       ===================================================== */
    const commandModal = document.getElementById('adminCommandModal');
    const commandOpen = document.getElementById('adminOpenCommand');
    const commandClose = document.getElementById('adminCloseCommand');
    const commandInput = document.getElementById('adminCommandInput');

    const openCommand = () => {
        if (!commandModal) return;
        commandModal.classList.add('is-open');
        commandModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => commandInput?.focus(), 30);
    };

    const closeCommand = () => {
        if (!commandModal) return;
        commandModal.classList.remove('is-open');
        commandModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    commandOpen?.addEventListener('click', openCommand);
    commandClose?.addEventListener('click', closeCommand);
    commandModal?.addEventListener('click', (event) => {
        if (event.target === commandModal) closeCommand();
    });

    commandInput?.addEventListener('input', () => {
        const query = commandInput.value.trim().toLocaleLowerCase('fr');
        commandModal?.querySelectorAll('.admin-command-card').forEach((card) => {
            const matches = !query || (card.textContent || '').toLocaleLowerCase('fr').includes(query);
            card.hidden = !matches;
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCommand();
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openCommand();
        }
    });

    /* =====================================================
       ONGLETS
       ===================================================== */
    const tabButtons = Array.from(root.querySelectorAll('[data-admin-tab]'));
    const tabPanels = Array.from(root.querySelectorAll('[data-admin-panel]'));

    const resizeCharts = () => {
        window.setTimeout(() => {
            charts.forEach((chart) => chart.resize());
        }, 80);
    };

    const activateTab = (key, scrollToTabs = false) => {
        tabButtons.forEach((button) => {
            const active = button.dataset.adminTab === key;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        tabPanels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.adminPanel === key);
        });

        closeCommand();
        resizeCharts();

        if (scrollToTabs) {
            root.querySelector('.admin-tabs-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => activateTab(button.dataset.adminTab || 'overview'));
    });

    root.querySelectorAll('[data-open-admin-tab]').forEach((button) => {
        button.addEventListener('click', () => activateTab(button.dataset.openAdminTab || 'overview', true));
    });

    /* =====================================================
       RECHERCHE GLOBALE
       ===================================================== */
    const searchInput = document.getElementById('adminSearch');
    const searchResults = document.getElementById('globalSearchResults');
    const searchResultsContent = document.getElementById('searchResultsContent');
    const searchUrl = dashboardData.searchUrl || '';
    let searchTimer = null;
    let searchController = null;

    const closeSearch = () => {
        if (searchResults) searchResults.hidden = true;
    };

    const appendSearchGroup = (fragment, title, icon, items) => {
        if (!Array.isArray(items) || items.length === 0) return;

        const group = document.createElement('section');
        group.className = 'admin-search-result__group';

        const heading = document.createElement('div');
        heading.className = 'admin-search-result__title';
        heading.textContent = `${icon} ${title}`;
        group.appendChild(heading);

        items.forEach((item) => {
            const link = document.createElement('a');
            link.className = 'admin-search-result__item';
            link.href = item.url || '#';
            link.textContent = item.label || item.email || item.nom || item.title || item.reference || '—';
            group.appendChild(link);
        });

        fragment.appendChild(group);
    };

    const renderSearchResults = (payload) => {
        if (!searchResults || !searchResultsContent) return;

        searchResultsContent.replaceChildren();
        const fragment = document.createDocumentFragment();

        appendSearchGroup(fragment, i18n.users || 'Utilisateurs', '👤', payload.users);
        appendSearchGroup(fragment, i18n.companies || 'Entreprises', '🏢', payload.companies);
        appendSearchGroup(fragment, i18n.jobs || 'Offres', '📣', payload.jobs);
        appendSearchGroup(fragment, i18n.transactions || 'Transactions', '💳', payload.transactions);
        appendSearchGroup(fragment, i18n.tickets || 'Tickets', '🎫', payload.tickets);

        if (!fragment.childNodes.length) {
            const empty = document.createElement('div');
            empty.className = 'admin-empty';
            empty.textContent = i18n.noResult || 'Aucun résultat';
            fragment.appendChild(empty);
        }

        searchResultsContent.appendChild(fragment);
        searchResults.hidden = false;
    };

    const runSearch = async (query) => {
        if (!searchUrl) return;

        searchController?.abort();
        searchController = new AbortController();

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: searchController.signal
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            renderSearchResults(await response.json());
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (!searchResultsContent || !searchResults) return;
            searchResultsContent.textContent = i18n.searchError || 'Erreur lors de la recherche globale';
            searchResults.hidden = false;
        }
    };

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim();
        window.clearTimeout(searchTimer);

        if (query.length < 2) {
            closeSearch();
            return;
        }

        searchTimer = window.setTimeout(() => runSearch(query), 260);
    });

    document.addEventListener('click', (event) => {
        if (!searchResults || !searchInput) return;
        if (!searchResults.contains(event.target) && event.target !== searchInput) closeSearch();
    });

    /* =====================================================
       GRAPHIQUES
       ===================================================== */
    if (typeof window.Chart === 'undefined') return;

    const gridColor = 'rgba(138, 149, 168, .16)';
    const textColor = '#6b7688';
    const primary = '#3157d5';
    const cyan = '#1f7ea8';
    const success = '#17875f';
    const warning = '#b46a12';
    const danger = '#c4414f';
    const violet = '#7455c7';

    const commonPlugins = {
        legend: {
            labels: {
                color: textColor,
                boxWidth: 9,
                boxHeight: 9,
                usePointStyle: true,
                font: { size: 11, weight: 500 }
            }
        },
        tooltip: {
            backgroundColor: '#ffffff',
            titleColor: '#172033',
            bodyColor: '#5d687b',
            borderColor: '#e6eaf1',
            borderWidth: 1,
            padding: 10,
            displayColors: true
        }
    };

    const cartesianOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: commonPlugins,
        scales: {
            x: {
                ticks: { color: textColor, maxRotation: 0, autoSkip: true },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                ticks: { color: textColor, precision: 0 },
                grid: { color: gridColor }
            }
        }
    };

    const createChart = (id, config) => {
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        const previous = charts.get(id);
        previous?.destroy();
        const chart = new window.Chart(canvas, config);
        charts.set(id, chart);
        return chart;
    };

    const split = chartData.usersSplit || {};
    createChart('chartUsersSplit', {
        type: 'doughnut',
        data: {
            labels: ['Company', 'Talent', 'Particulier', 'Staff'],
            datasets: [{
                data: [toNumber(split.company), toNumber(split.talent), toNumber(split.particulier), toNumber(split.staff)],
                backgroundColor: [primary, cyan, success, warning],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false }, tooltip: commonPlugins.tooltip }
        }
    });

    const jobs = chartData.jobs || {};
    createChart('chartJobs', {
        type: 'bar',
        data: {
            labels: [i18n.jobs || 'Offres', i18n.recruited || 'Recrutés'],
            datasets: [{
                label: i18n.volume || 'Volume',
                data: [toNumber(jobs.jobs), toNumber(jobs.recruited)],
                backgroundColor: [cyan, success],
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 62
            }]
        },
        options: { ...cartesianOptions, plugins: { ...commonPlugins, legend: { display: false } } }
    });

    const moderation = chartData.moderation || {};
    createChart('chartModeration', {
        type: 'radar',
        data: {
            labels: [i18n.spam || 'Spam', i18n.abuse || 'Abus', i18n.fraud || 'Fraude', i18n.identity || 'Identité', i18n.other || 'Autres'],
            datasets: [{
                label: i18n.reports || 'Signalements',
                data: [moderation.spam, moderation.abuse, moderation.fraud, moderation.identity, moderation.other].map(toNumber),
                borderColor: danger,
                backgroundColor: 'rgba(196, 65, 79, .10)',
                pointBackgroundColor: danger,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: commonPlugins,
            scales: {
                r: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    angleLines: { color: gridColor },
                    pointLabels: { color: textColor, font: { size: 10 } },
                    ticks: { display: false }
                }
            }
        }
    });

    const revenue = chartData.revenue || {};
    createChart('chartRevenue', {
        type: 'line',
        data: {
            labels: Array.isArray(revenue.labels) ? revenue.labels : [],
            datasets: [{
                label: 'RMR',
                data: Array.isArray(revenue.values) ? revenue.values.map(toNumber) : [],
                borderColor: success,
                backgroundColor: 'rgba(23, 135, 95, .08)',
                fill: true,
                tension: 0.36,
                pointRadius: 2,
                pointHoverRadius: 4,
                borderWidth: 2
            }]
        },
        options: {
            ...cartesianOptions,
            plugins: {
                ...commonPlugins,
                tooltip: {
                    ...commonPlugins.tooltip,
                    callbacks: {
                        label: (context) => `${context.dataset.label}: ${toNumber(context.raw).toLocaleString('fr-FR')} XAF`
                    }
                }
            },
            scales: {
                ...cartesianOptions.scales,
                y: {
                    ...cartesianOptions.scales.y,
                    ticks: {
                        color: textColor,
                        callback: (value) => toNumber(value).toLocaleString('fr-FR')
                    }
                }
            }
        }
    });

    const plans = chartData.plans || {};
    createChart('chartPlans', {
        type: 'doughnut',
        data: {
            labels: Array.isArray(plans.labels) ? plans.labels : [],
            datasets: [{
                data: Array.isArray(plans.values) ? plans.values.map(toNumber) : [],
                backgroundColor: [primary, cyan, success, warning, violet],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false }, tooltip: commonPlugins.tooltip }
        }
    });

    const providers = chartData.providers || {};
    createChart('chartProviders', {
        type: 'doughnut',
        data: {
            labels: Array.isArray(providers.labels) ? providers.labels : [],
            datasets: [{
                data: Array.isArray(providers.values) ? providers.values.map(toNumber) : [],
                backgroundColor: [success, warning, primary, cyan, violet],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { display: false }, tooltip: commonPlugins.tooltip }
        }
    });

    const trend = chartData.userTrend || {};
    createChart('chartHealth', {
        type: 'line',
        data: {
            labels: Array.isArray(trend.labels) ? trend.labels : [],
            datasets: [
                {
                    label: 'TALENTS',
                    data: Array.isArray(trend.talent) ? trend.talent.map(toNumber) : [],
                    borderColor: cyan,
                    backgroundColor: 'rgba(31, 126, 168, .08)',
                    tension: 0.35,
                    pointRadius: 2,
                    borderWidth: 2
                },
                {
                    label: 'PARTICULIERS',
                    data: Array.isArray(trend.particulier) ? trend.particulier.map(toNumber) : [],
                    borderColor: success,
                    backgroundColor: 'rgba(23, 135, 95, .08)',
                    tension: 0.35,
                    pointRadius: 2,
                    borderWidth: 2
                },
                {
                    label: 'ENTREPRISES',
                    data: Array.isArray(trend.company) ? trend.company.map(toNumber) : [],
                    borderColor: primary,
                    backgroundColor: 'rgba(49, 87, 213, .08)',
                    tension: 0.35,
                    pointRadius: 2,
                    borderWidth: 2
                }
            ]
        },
        options: cartesianOptions
    });

    /* Courbe de croissance avec changement 30j / 90j / 12m */
    const growthSource = chartData.growth || {};
    const growthCanvas = document.getElementById('chartSignups');
    const growthButtons = Array.from(root.querySelectorAll('[data-growth-range]'));

    if (growthCanvas) {
        const context = growthCanvas.getContext('2d');
        const gradient = context.createLinearGradient(0, 0, 0, 350);
        gradient.addColorStop(0, 'rgba(49, 87, 213, .16)');
        gradient.addColorStop(1, 'rgba(49, 87, 213, .01)');

        const growthChart = createChart('chartSignups', {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: i18n.signups || 'Inscriptions',
                    data: [],
                    borderColor: primary,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.38,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    borderWidth: 2
                }]
            },
            options: cartesianOptions
        });

        const rangeHasData = (range) => Array.isArray(growthSource?.[range]?.labels) && growthSource[range].labels.length > 0;
        const defaultRange = rangeHasData('12') ? '12' : (rangeHasData('30') ? '30' : '90');

        const applyGrowthRange = (range) => {
            if (!growthChart) return;
            const payload = growthSource[range] || { labels: [], signups: [] };
            growthChart.data.labels = Array.isArray(payload.labels) ? payload.labels : [];
            growthChart.data.datasets[0].data = Array.isArray(payload.signups) ? payload.signups.map(toNumber) : [];
            growthChart.update();

            growthButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.growthRange === range);
            });
        };

        growthButtons.forEach((button) => {
            button.addEventListener('click', () => applyGrowthRange(button.dataset.growthRange || defaultRange));
        });

        applyGrowthRange(defaultRange);
    }
})();
