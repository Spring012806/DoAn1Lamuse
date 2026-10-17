const password = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");
const loginForm = document.getElementById("loginForm");
const formMessage = document.getElementById("formMessage");

togglePassword.addEventListener("click", function () {
  const isHidden = password.type === "password";

  password.type = isHidden ? "text" : "password";
  togglePassword.textContent = isHidden ? "Ẩn" : "Hiện";
  togglePassword.setAttribute(
    "aria-label",
    isHidden ? "Ẩn mật khẩu" : "Hiện mật khẩu",
  );
});

loginForm.addEventListener("submit", function (event) {
  event.preventDefault();

  formMessage.textContent =
    "Giao diện đã nhận thông tin. Cần kết nối hệ thống tài khoản để đăng nhập.";
});
