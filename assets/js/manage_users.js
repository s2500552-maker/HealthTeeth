// Shows the licence number field only for dentists (the server ignores it for other roles).
(() => {
  const role = document.getElementById('role');
  const field = document.getElementById('license-field');
  if (!role || !field) return;
  const sync = () => { field.hidden = role.value !== 'dentist'; };
  role.addEventListener('change', sync);
  sync();
})();
