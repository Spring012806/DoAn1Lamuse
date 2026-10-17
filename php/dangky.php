<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Đăng ký | La Muse</title>

    <!-- KẾT NỐI CSS RIÊNG CỦA TRANG ĐĂNG KÝ -->
    <link rel="stylesheet" href="../css/dangki.css" />

    <!-- KẾT NỐI GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link
      href="https://fonts.googleapis.com/css2?family=Agbalumo&family=Alice&family=Pacifico&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Playwrite+AR:wght@100..400&family=Playwrite+CA+Guides&family=Playwrite+CU+Guides&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
      rel="stylesheet"
    />
  </head>

  <body>
    <!-- KHUNG TỔNG: HÌNH TRÁI - FORM PHẢI -->
    <main class="register-layout">
      <!-- KHUNG HÌNH BÊN TRÁI -->
      <section class="register-image">
        <img
          src="../picture/Hinhtrangdangki.jpg"
          alt="Bộ sưu tập thời trang La Muse"
        />

        <div class="image-overlay">
          <p class="image-label">LA MUSE</p>

          <h2>
            Elegance in<br />
            Every Moment
          </h2>

          <p>Khám phá vẻ đẹp riêng của bạn.</p>
        </div>
      </section>

      <!-- KHUNG FORM ĐĂNG KÝ BÊN PHẢI -->
      <section class="register-container">
        <a href="../index.html" class="logo"> La Muse </a>

        <h1>Tạo tài khoản</h1>

        <p class="subtitle">Khám phá phong cách của riêng bạn cùng La Muse.</p>

        <form id="registerForm">
          <label for="fullname">Họ và tên</label>
          <input
            type="text"
            id="fullname"
            name="fullname"
            placeholder="Nhập họ và tên"
            autocomplete="name"
            required
          />

          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            placeholder="example@email.com"
            autocomplete="email"
            required
          />

          <label for="password">Mật khẩu</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Ít nhất 8 ký tự"
            minlength="8"
            autocomplete="new-password"
            required
          />

          <label for="confirmPassword"> Xác nhận mật khẩu </label>
          <input
            type="password"
            id="confirmPassword"
            name="confirmPassword"
            placeholder="Nhập lại mật khẩu"
            minlength="8"
            autocomplete="new-password"
            required
          />

          <label class="terms">
            <input type="checkbox" id="agree" required />
            <span> Tôi đồng ý với điều khoản sử dụng. </span>
          </label>

          <button type="submit">ĐĂNG KÝ</button>

          <p id="message" role="status"></p>
        </form>

        <p class="login-link">
          Đã có tài khoản?
          <a href="dangnhap.php">Đăng nhập</a>
        </p>
      </section>
    </main>

    <!-- KẾT NỐI JAVASCRIPT -->
    <script src="../JS/dangky.js" defer></script>
  </body>
</html>
