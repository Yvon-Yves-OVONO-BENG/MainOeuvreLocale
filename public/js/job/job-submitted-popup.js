(() => {
  'use strict';
  const boot = () => {
    const config = document.getElementById('molJobSubmittedPopup');
    if (!config || typeof window.Swal === 'undefined') return;
    const popupKey = config.dataset.popupKey || '';
    window.__molDisplayedJobPopups = window.__molDisplayedJobPopups || {};
    if (!popupKey || window.__molDisplayedJobPopups[popupKey]) return;
    try {
      if (window.sessionStorage.getItem(popupKey) === '1') return;
      window.sessionStorage.setItem(popupKey, '1');
    } catch (error) {}
    window.__molDisplayedJobPopups[popupKey] = true;
    window.Swal.fire({
      html: config.dataset.popupHtml || '',
      confirmButtonText: config.dataset.confirmText || 'Compris',
      confirmButtonColor: '#6366f1',
      allowOutsideClick: false,
      width: 600,
      padding: '1.5rem',
      backdrop: 'rgba(0,0,0,.5)'
    });
  };
  document.addEventListener('DOMContentLoaded', boot, {once:true});
  document.addEventListener('turbo:load', boot);
})();
