'use strict';

document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-toggle-password]');
  if (!button) return;
  const input = document.getElementById(button.dataset.togglePassword);
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'Ẩn' : 'Hiện';
  button.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
});

document.querySelectorAll('[data-auth-form]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    const password = form.querySelector('#password');
    const confirm = form.querySelector('#confirm_password');
    if (password && password.value.length < 8) {
      event.preventDefault();
      password.setCustomValidity('Mật khẩu cần có ít nhất 8 ký tự.');
      password.reportValidity();
      password.addEventListener('input', () => password.setCustomValidity(''), { once: true });
      return;
    }
    if (password && confirm && password.value !== confirm.value) {
      event.preventDefault();
      confirm.setCustomValidity('Mật khẩu xác nhận chưa khớp.');
      confirm.reportValidity();
      confirm.addEventListener('input', () => confirm.setCustomValidity(''), { once: true });
    }
  });
});
