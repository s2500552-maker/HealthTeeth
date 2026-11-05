// Screen 3 interactions: PIN boxes, paste, backspace navigation, resend countdown.
// The server re-validates everything; this file only improves the experience.
(() => {
  const form = document.getElementById('verify-form');
  const boxes = Array.from(document.querySelectorAll('.pin-box'));
  const message = document.getElementById('pin-error');
  if (!form || boxes.length !== 6 || !message) return;

  const setMessage = (text, isError) => {
    message.textContent = text;
    message.className = isError ? 'pin-message field-error' : 'pin-message';
    boxes.forEach((box) => box.classList.toggle('is-invalid', isError));
  };

  const clearErrorOnEdit = () => {
    if (message.classList.contains('field-error')) {
      setMessage('Enter the 6-digit code', false);
    }
  };

  const fillFrom = (start, digits) => {
    digits.split('').slice(0, boxes.length - start).forEach((digit, k) => {
      boxes[start + k].value = digit;
    });
    const next = boxes.find((box) => !box.value) || boxes[boxes.length - 1];
    next.focus();
    clearErrorOnEdit();
  };

  boxes.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      if (box.value && boxes[i + 1]) boxes[i + 1].focus();
      clearErrorOnEdit();
    });

    box.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !box.value && boxes[i - 1]) {
        event.preventDefault();
        boxes[i - 1].value = '';
        boxes[i - 1].focus();
      } else if (event.key === 'ArrowLeft' && boxes[i - 1]) {
        event.preventDefault();
        boxes[i - 1].focus();
      } else if (event.key === 'ArrowRight' && boxes[i + 1]) {
        event.preventDefault();
        boxes[i + 1].focus();
      }
    });

    box.addEventListener('paste', (event) => {
      event.preventDefault();
      const digits = (event.clipboardData.getData('text') || '').replace(/\D/g, '');
      if (digits) fillFrom(0, digits);
    });

    box.addEventListener('focus', () => box.select());
  });

  form.addEventListener('submit', (event) => {
    const code = boxes.map((box) => box.value).join('');
    if (!/^\d{6}$/.test(code)) {
      event.preventDefault();
      setMessage('Please enter the complete 6-digit verification code.', true);
      const firstEmpty = boxes.find((box) => !box.value);
      if (firstEmpty) firstEmpty.focus();
    }
  });

  // Resend countdown (the server enforces the same cooldown).
  const resendButton = document.getElementById('resend-btn');
  const timer = document.getElementById('resend-timer');
  if (resendButton && timer) {
    let remaining = Number(resendButton.dataset.seconds) || 0;
    const format = (s) => `(${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')})`;
    const render = () => {
      resendButton.disabled = remaining > 0;
      timer.textContent = remaining > 0 ? ' ' + format(remaining) : '';
    };

    render();
    if (remaining > 0) {
      const handle = setInterval(() => {
        remaining -= 1;
        render();
        if (remaining <= 0) clearInterval(handle);
      }, 1000);
    }
  }
})();
