document.addEventListener('submit', function (e) {
      const form = e.target.closest('.js-swal-confirm');
      if (!form) return;

      e.preventDefault();

      const title = form.dataset.title || 'Confirmer ?';
      const text = form.dataset.text || 'Voulez-vous continuer ?';
      const icon = form.dataset.icon || 'warning';
      const confirmText = form.dataset.confirm || 'Oui';
      const confirmColor = form.dataset.confirmColor || '#dc2626';

      Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Non',
        reverseButtons: true,
        confirmButtonColor: confirmColor
      }).then((res) => {
        if (res.isConfirmed) form.submit();
      });
    });
