document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-admin-ops]');
  if (!root) return;

  const endpointTemplate = root.dataset.endpointTemplate;
  const csrfToken = root.dataset.csrfToken;
  const toast = document.querySelector('[data-sa-toast]');

  const showToast = (message, type = 'success') => {
    if (!toast) return;

    toast.textContent = message;
    toast.className = `sa-toast ${type} show`;

    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(() => {
      toast.className = `sa-toast ${type}`;
    }, 2600);
  };

  const updateMaintenanceUi = (enabled) => {
    const badge = document.querySelector('[data-maintenance-badge]');
    const status = document.querySelector('[data-status-maintenance]');

    if (badge) {
      badge.dataset.maintenanceState = enabled ? 'on' : 'off';
      badge.textContent = enabled ? '⛔ ON' : '✅ OFF';
      badge.style.background = enabled ? 'rgba(239,68,68,.18)' : 'rgba(16,185,129,.18)';
      badge.style.color = enabled ? '#fee2e2' : '#d1fae5';
    }

    if (status) {
      status.textContent = enabled ? 'ON' : 'OFF';
      status.classList.remove('sa-status-success', 'sa-status-danger');
      status.classList.add(enabled ? 'sa-status-danger' : 'sa-status-success');
    }
  };

  const bindAction = (button) => {
    button.addEventListener('click', async () => {
      const action = button.dataset.saActionCode;
      const confirmMessage = button.dataset.saConfirm || '';
      const originalHtml = button.innerHTML;

      if (!action) return;

      if (confirmMessage && !window.confirm(confirmMessage)) {
        return;
      }

      button.disabled = true;
      button.classList.add('is-loading');
      button.innerHTML = `<span>⏳</span><span>Processing...</span>`;

      try {
        const response = await fetch(
          endpointTemplate.replace('__ACTION__', action),
          {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json'
            }
          }
        );

        const data = await response.json();

        if (!response.ok || !data.ok) {
          throw new Error(data.message || 'Opération impossible.');
        }

        if (action === 'toggle_maintenance' && data.meta && typeof data.meta.maintenance !== 'undefined') {
          updateMaintenanceUi(Boolean(data.meta.maintenance));
        }

        if (action === 'reindex_search' && data.meta && data.meta.lastReindexAt) {
          const target = document.querySelector('[data-last-reindex]');
          if (target) target.textContent = data.meta.lastReindexAt;
        }

        if (action === 'restart_notifications_queue' && data.meta) {
          const target = document.querySelector('[data-last-queue-restart]');
          if (target && data.meta.lastQueueRestartAt) {
            target.textContent = data.meta.lastQueueRestartAt;
          }

          const queue = document.querySelector('[data-status-queue]');
          if (queue && data.meta.queueHealth) {
            queue.textContent = data.meta.queueHealth;
          }
        }

        if (action === 'retry_webhooks' && data.meta && data.meta.lastWebhookRetryAt) {
          const target = document.querySelector('[data-last-webhook-retry]');
          if (target) target.textContent = data.meta.lastWebhookRetryAt;
        }

        showToast(data.message || 'Action exécutée.', 'success');
      } catch (error) {
        showToast(error.message || 'Une erreur est survenue.', 'error');
      } finally {
        button.disabled = false;
        button.classList.remove('is-loading');
        button.innerHTML = originalHtml;
      }
    });
  };

  root.querySelectorAll('[data-sa-action-code]').forEach(bindAction);
});