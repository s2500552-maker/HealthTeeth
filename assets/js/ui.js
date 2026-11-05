// Shared: dismissible alerts.
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-dismiss]');
  if (!button) return;
  const alert = button.closest('.alert');
  if (alert) alert.hidden = true;
});
