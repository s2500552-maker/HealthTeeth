// Password rules, live checklist and inline/banner validation (reset password + sign-up).
// PHP validates again on submit; this file only gives faster feedback.
(() => {
  const form = document.getElementById('reset-form');
  if (!form) return;

  const password = document.getElementById('password');
  const confirm = document.getElementById('confirm_password');
  const passwordError = document.getElementById('password-error');
  const confirmError = document.getElementById('confirm-error');
  const reqList = document.getElementById('password-reqs');
  const banner = document.getElementById('form-alert'); // only on the reset page

  const rules = {
    length: (v) => v.length >= 8,
    upper: (v) => /[A-Z]/.test(v),
    lower: (v) => /[a-z]/.test(v),
    number: (v) => /\d/.test(v),
    special: (v) => /[^A-Za-z0-9]/.test(v),
  };

  // The most relevant message, in the same order as the server.
  const firstPasswordError = (v) => {
    if (v.length < 8) return 'Password must be at least 8 characters.';
    if (!/[A-Z]/.test(v)) return 'Your password must contain an uppercase letter.';
    if (!/[a-z]/.test(v)) return 'Your password must contain a lowercase letter.';
    if (!/\d/.test(v)) return 'Your password must contain a number.';
    if (!/[^A-Za-z0-9]/.test(v)) return 'Your password must contain a special character.';
    return '';
  };

  const MISMATCH_BANNER = 'Passwords do not match.';
  const MISMATCH_INLINE = 'The passwords you entered do not match.';
  const confirmMismatch = () => (confirm.value !== password.value);

  const mark = (input, bad) => {
    input.classList.toggle('is-invalid', bad);
    input.setAttribute('aria-invalid', String(bad));
  };

  const showBanner = (text) => {
    if (!banner) return;
    banner.querySelector('.alert-text').textContent = text;
    banner.hidden = !text;
  };

  // Checklist: neutral until typing starts, then green (met) or red (not met).
  const updateChecklist = () => {
    if (!reqList) return;
    const typed = password.value.length > 0;
    if (typed) reqList.hidden = false;
    reqList.querySelectorAll('[data-rule]').forEach((item) => {
      const met = rules[item.dataset.rule](password.value);
      item.classList.toggle('is-met', typed && met);
      item.classList.toggle('is-unmet', typed && !met);
    });
  };

  const clearPasswordError = () => {
    mark(password, false);
    if (passwordError) { passwordError.hidden = true; passwordError.textContent = ''; }
  };
  const clearConfirmError = () => {
    mark(confirm, false);
    confirmError.hidden = true;
    confirmError.textContent = '';
  };

  password.addEventListener('input', () => {
    updateChecklist();
    if (password.classList.contains('is-invalid') && !firstPasswordError(password.value)) {
      clearPasswordError();
      if (banner && !banner.hidden) showBanner('');
    }
    if (confirm.classList.contains('is-invalid') && !confirmMismatch()) clearConfirmError();
  });

  confirm.addEventListener('input', () => {
    if (confirm.classList.contains('is-invalid') && !confirmMismatch()) {
      clearConfirmError();
      if (banner && !banner.hidden) showBanner('');
    }
  });
  confirm.addEventListener('blur', () => {
    if (confirm.value && password.value && confirmMismatch()) {
      mark(confirm, true);
      confirmError.textContent = MISMATCH_INLINE;
      confirmError.hidden = false;
    }
  });

  form.addEventListener('submit', (event) => {
    const pwError = firstPasswordError(password.value);
    const mismatch = !pwError && confirmMismatch();

    mark(password, Boolean(pwError));
    mark(confirm, mismatch);
    confirmError.hidden = !mismatch;
    confirmError.textContent = mismatch ? MISMATCH_INLINE : '';
    updateChecklist();

    if (pwError || mismatch) {
      event.preventDefault();
      showBanner(pwError || MISMATCH_BANNER);
      if (!banner && pwError && passwordError) { passwordError.textContent = pwError; passwordError.hidden = false; }
      (pwError ? password : confirm).focus();
    }
  });

  updateChecklist();
})();
