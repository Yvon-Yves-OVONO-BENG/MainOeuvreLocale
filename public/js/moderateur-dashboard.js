(() => {
  'use strict';

  const root = document.querySelector('[data-moderator-dashboard]');
  if (!root) return;

  const loadingElements = new Set();
  let chartsInitialized = false;

  const setLoading = (element, active = true) => {
    if (!(element instanceof HTMLElement)) return;

    if (active) {
      if (element.classList.contains('is-loading')) return;

      element.classList.add('is-loading');
      element.setAttribute('aria-busy', 'true');
      loadingElements.add(element);

      if (element instanceof HTMLButtonElement) {
        element.dataset.wasDisabled = element.disabled ? '1' : '0';
        element.disabled = true;
      }
    } else {
      element.classList.remove('is-loading');
      element.removeAttribute('aria-busy');
      loadingElements.delete(element);

      if (element instanceof HTMLButtonElement && element.dataset.wasDisabled !== '1') {
        element.disabled = false;
      }
      delete element.dataset.wasDisabled;
    }
  };

  const resetLoaders = () => {
    loadingElements.forEach((element) => setLoading(element, false));
    root.querySelectorAll('.is-loading').forEach((element) => setLoading(element, false));
  };

  const isModifiedClick = (event) => (
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  );

  const isNavigableLink = (link) => {
    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript:')) return false;
    if (link.hasAttribute('download')) return true;
    if (link.target && link.target.toLowerCase() === '_blank') return false;
    return true;
  };

  root.addEventListener('click', (event) => {
    const chatButton = event.target.closest('[data-open-chat]');
    if (chatButton) {
      event.preventDefault();
      setLoading(chatButton, true);

      window.setTimeout(() => {
        try {
          if (typeof window.MOLChatOpen === 'function') {
            window.MOLChatOpen();
          }
        } finally {
          setLoading(chatButton, false);
        }
      }, 180);
      return;
    }

    const toggleButton = event.target.closest('[data-panel-toggle]');
    if (toggleButton) {
      event.preventDefault();
      setLoading(toggleButton, true);

      window.setTimeout(() => {
        togglePanel(toggleButton);
        setLoading(toggleButton, false);
      }, 160);
      return;
    }

    const link = event.target.closest('a[href]');
    if (!link || isModifiedClick(event) || !isNavigableLink(link)) return;

    setLoading(link, true);
  });

  root.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
      }

      const submitter = event.submitter || form.querySelector('[type="submit"]');
      if (submitter) setLoading(submitter, true);
    });
  });

  const togglePanel = (button) => {
    const selector = button.getAttribute('data-panel-toggle');
    const panel = selector ? document.querySelector(selector) : null;
    if (!panel) return;

    const opening = !panel.classList.contains('is-open');
    panel.classList.toggle('is-open', opening);
    panel.setAttribute('aria-hidden', opening ? 'false' : 'true');
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
    button.textContent = opening
      ? (button.dataset.labelClose || 'Masquer')
      : (button.dataset.labelOpen || 'Afficher');

    if (opening) {
      initializeCharts();
      window.setTimeout(() => {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 260);
    }
  };

  const readDashboardData = () => {
    const dataNode = document.getElementById('moderatorDashboardData');
    if (!dataNode) return null;

    try {
      return JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
      console.error('Moderator dashboard: données analytics invalides.', error);
      return null;
    }
  };

  const chartDefaults = () => {
    if (!window.Chart) return;

    window.Chart.defaults.font.family = 'Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    window.Chart.defaults.color = '#697489';
    window.Chart.defaults.borderColor = 'rgba(34, 46, 69, 0.08)';
  };

  const initializeCharts = () => {
    if (chartsInitialized || !window.Chart) return;

    const dashboardData = readDashboardData();
    if (!dashboardData) return;

    chartsInitialized = true;
    chartDefaults();

    const signups = dashboardData.signups7d || { labels: [], values: [] };
    const gender = dashboardData.gender7d || { F: 0, M: 0, PM: 0 };
    const roles = dashboardData.roles7d || {};

    const signupsCanvas = document.getElementById('chartSignups7d');
    if (signupsCanvas) {
      new window.Chart(signupsCanvas, {
        type: 'line',
        data: {
          labels: Array.isArray(signups.labels) ? signups.labels : [],
          datasets: [{
            label: 'Inscriptions',
            data: Array.isArray(signups.values) ? signups.values : [],
            borderColor: '#3157d5',
            backgroundColor: 'rgba(49, 87, 213, 0.10)',
            pointBackgroundColor: '#3157d5',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 3,
            borderWidth: 2,
            tension: 0.35,
            fill: true
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { intersect: false, mode: 'index' },
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
          }
        }
      });
    }

    const genderCanvas = document.getElementById('chartGender7d');
    if (genderCanvas) {
      new window.Chart(genderCanvas, {
        type: 'doughnut',
        data: {
          labels: ['F', 'M', 'PM'],
          datasets: [{
            data: [
              Number(gender.F || 0),
              Number(gender.M || 0),
              Number(gender.PM || 0)
            ],
            backgroundColor: ['#3157d5', '#17875f', '#d68a2f'],
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 }
            }
          }
        }
      });
    }

    const rolesCanvas = document.getElementById('chartRoles7d');
    if (rolesCanvas) {
      new window.Chart(rolesCanvas, {
        type: 'bar',
        data: {
          labels: ['Talent', 'Particulier', 'Compagnie'],
          datasets: [{
            label: 'Inscriptions',
            data: [
              Number(roles.ROLE_TALENT || 0),
              Number(roles.ROLE_PARTICULIER || 0),
              Number(roles.ROLE_COMPANY || 0)
            ],
            backgroundColor: ['#3157d5', '#17875f', '#d68a2f'],
            borderRadius: 7,
            borderSkipped: false,
            maxBarThickness: 42
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
          }
        }
      });
    }
  };

  const waitForChartJs = (attempt = 0) => {
    if (window.Chart) return;
    if (attempt >= 30) {
      console.warn('Moderator dashboard: Chart.js ne s’est pas chargé.');
      return;
    }
    window.setTimeout(() => waitForChartJs(attempt + 1), 100);
  };

  window.addEventListener('pageshow', resetLoaders);
  window.addEventListener('beforeunload', () => {
    root.querySelectorAll('a[href].is-loading').forEach((link) => link.setAttribute('aria-disabled', 'true'));
  });

  waitForChartJs();
})();
