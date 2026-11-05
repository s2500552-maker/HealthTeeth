// Show/hide toggle for password fields. The icon swap is done in CSS from aria-pressed.
document.querySelectorAll('.toggle-visibility').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.target);
    if (!input) return;
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(reveal));
    button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  });
});
