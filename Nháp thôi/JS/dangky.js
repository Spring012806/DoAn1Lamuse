const form = document.getElementById("registerForm");
const password = document.getElementById("password");
const confirmPassword = document.getElementById("confirmPassword");
const message = document.getElementById("message");

form.addEventListener("submit", function (event) {
  event.preventDefault();

  if (password.value !== confirmPassword.value) {
    message.textContent = "Mật khẩu xác nhận không khớp!";
    message.style.color = "#b42318";
    return;
  }

  message.textContent =
    "Kiểm tra thành công! Bạn cần kết nối máy chủ để tạo tài khoản.";
  message.style.color = "#306D29";
});
