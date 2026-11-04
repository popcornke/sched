document.addEventListener('submit', function (event) {
  const form = event.target.closest('form[data-history-confirm]');
  if (!form) return;
  const message = form.dataset.historyConfirm;
  if (message && !window.confirm(message)) event.preventDefault();
});
