<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Đăng nhập | La Muse</title>

    

    <!--KẾT NỐI VỚI GG FONTFONT-->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Agbalumo&family=Alice&family=Pacifico&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Playwrite+AR:wght@100..400&family=Playwrite+CA+Guides&family=Playwrite+CU+Guides&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
      rel="stylesheet"
    />
    <!-- KẾT NỐI CSS -->
    <link rel="stylesheet" href="../css/dangnhap.css" />
   
  </head>

  <body>
    <main class="login-page">
      <section class="login-card">
        <a href="#" class="brand">
          <span>LA MUSE</span>
        </a>

        <p class="brand-caption">Fashion your for story</p>

        <div class="heading">
          <p class="eyebrow">WELCOME BACK</p>
          <h1>Chào mừng trở lại!</h1>
          <p class="description">Đăng nhập để tiếp tục hành trình của bạn.</p>
        </div>

        <form id="loginForm">
          <div class="form-group">
            <label for="email">Email của bạn</label>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="name@example.com"
              autocomplete="email"
              required
            />
          </div>

          <div class="form-group">
            <label for="password">Mật khẩu</label>

            <div class="password-box">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Nhập mật khẩu nha gái iu"
                autocomplete="current-password"
                required
              />

              <button
                type="button"
                class="toggle-password"
                id="togglePassword"
                aria-label="Hiện mật khẩu"
              >
                Hiện
              </button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember">
              <input type="checkbox" name="remember" />
              <span>Ghi nhớ đăng nhập</span>
            </label>

            <a href="#" class="forgot-password"> Quên mật khẩu? </a>
          </div>

          <button type="submit" class="login-button">ĐĂNG NHẬP</button>

          <p id="formMessage" class="form-message" role="status"></p>
        </form>

        <div class="divider">
          <span></span>
          <p>HOẶC</p>
          <span></span>
        </div>

        <p class="register-text">
          Bạn chưa có tài khoản hảa?
          <a href="dangky.php">Đăng ký ở đây nhe</a>
        </p>

        <p class="footer-text">© 2026 LA MUSE. FASHION FOR YOUR STORY</p>
      </section>

      <aside class="visual-panel">
        <!-- MẶT THỨ 2 BÊN PHẢI -->
        <div class="visual-content">
          <span class="visual-label">BÍ MẬT CỦA VẺ ĐẸP NẰM Ở SỰ TỰ TIN</span>

          <h2>Mỗi ngày là<br />một khởi đầu<br /><em>mới.</em></h2>

          <div class="visual-line"></div>

          <p>
            Dành một chút thời gian cho bản thân, và bắt đầu từ những điều giản
            dị nhất.
          </p>
        </div>
      </aside>
      <!-- KẾT NỐI JS -->
    
    <script src="JS/dangnhap.js" defer></script>
    </main>
  </body>
</html>
