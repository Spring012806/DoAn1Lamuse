<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <meta
      name="description"
      content="La Muse — Tisseuse de Rêves. Thời trang nữ mang tinh thần Pháp cổ điển, thơ mộng và tinh tế."
    />
    <title>La Muse — Tisseuse de Rêves</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&family=Parisienne&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../css/footer.php" />
    <link rel="stylesheet" href="../css/header.css" />
    <link rel="stylesheet" href="..//css/gioithieu.css" />

    <title>Giới thiệu | La Muse</title>

    <!--KẾT NỐI VỚI GG FONTFONT-->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Agbalumo&family=Alice&family=Pacifico&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Playwrite+AR:wght@100..400&family=Playwrite+CA+Guides&family=Playwrite+CU+Guides&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
      rel="stylesheet"
    />
  </head>

  <body>
    <!-- HEADER -->
    <div class="announcement">
      Une petite poésie au quotidien — Một chút thơ cho mỗi ngày
    </div>
    <header class="site-header" id="top">
      <button class="menu-toggle" aria-label="Mở menu" aria-expanded="false">
        ☰
      </button>
      <nav class="nav nav-left" aria-label="Điều hướng chính">
        <a class="active">Trang chủ</a>
        <a href="#products">Sản phẩm</a>
      </nav>

      <nav class="nav nav-right" aria-label="Thông tin">
        <a href="#">Giới thiệu</a>
        

        <button class="icon-button search-toggle" aria-label="Mở tìm kiếm">
          <svg viewBox="0 0 24 24">
            <circle cx="10.8" cy="10.8" r="6.5" />
            <path d="m16 16 4.2 4.2" />
          </svg>
        </button>

        <!-- KẾT NỐI VỚI TRANG GIỎ HÀNG -->
        <a href="giohang.php" class="icon-button cart-icon" id="cart-button" aria-label="Giỏ hàng">
              🛒
          <span id="cart-count">0</span>
        </a>

        <a class="account-link" href="dangnhap.php">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8" r="3.5" />
            <path d="M4.5 20c.5-4 3.2-6 7.5-6s7 2 7.5 6" />
          </svg>
          <span>Đăng nhập</span>
        </a>
      </nav>
      <form class="search-panel" id="search-form" role="search">
        <input
          id="search-input"
          type="search"
          placeholder="Tìm một món đồ bạn yêu..."
          aria-label="Tìm sản phẩm"
        />
        <button type="submit">Tìm kiếm</button>
        <button type="button" class="close-search" aria-label="Đóng tìm kiếm">
          ×
        </button>
      </form>
    </header>

    <main>
      <!-- BANNER 1 -->
      <section class="hero">
        <span class="icon one">✦</span>
        <span class="icon icon-two">✧</span>
        <span class="icon icon-three">✦</span>
        <span class="icon icon-four">✧</span>

        <p class="eyebrow">FASHION FOR YOUR STORY</p>

        <h1>
          Sự tinh tế
          <br />
          <em>có trong từng khoảnh khắc</em>
        </h1>

        <p class="hero-description">
          La Muse không chỉ là một thương hiệu thời trang, mà còn là người bạn
          đồng hành giúp bạn tự tin, tỏa sáng và là chính mình.
        </p>

        <a href="#story" class="hero-button">
          KHÁM PHÁ CÂU CHUYỆN
          <span>↓</span>
        </a>

        <div class="hero-decoration">La Muse · 2026</div>
      </section>

      <!-- BANNER HÌNH ẢNH  -->
      <section class="image-banner">
        <img src="../picture/BANNERHINHANH.jpg" />
      </section>

      <!-- BANNER 2 -->
      <!-- STORY -->
      <section class="story section" id="story">
        <div class="story-label">
          <p>FASHION FOR YOUR STORY</p>
        </div>

        <div class="story-content">
          <div class="story-heading">
            <p class="eyebrow">CÂU CHUYỆN CỦA CHÚNG TÔI</p>

            <h2>
              Bí mật của vẻ đẹp
              <br />
              nằm ở <em>sự tự tin</em>
            </h2>
          </div>

          <div class="story-text">
            <p>
              Trong nhịp sống hiện đại, đôi khi chúng ta quên mất việc dành thời
              gian để lắng nghe bản thân và trân trọng những điều đang hiện diện
              xung quanh.
            </p>

            <p>
              La Muse ra đời với mong muốn tôn vinh phái đẹp, nhẹ nhàng và đầy
              cảm hứng — nơi mỗi cô gái có thể tìm thấy giá trị trong những
              trang phục mà bạn mặc hằng ngày
            </p>

            <p>
              Với chúng tôi, trang phục không chỉ là quần áo, nó còn mang tâm
              hồn của người khoác lên nó. Trang phục nói lên tính cách của mỗi
              người, khi bạn xinh đẹp theo cách bạn muốn, bạn sẽ thấy tự tin hơn
            </p>
          </div>
        </div>
      </section>

      <!-- BANNER 3 -->
      <section class="philosophy section">
        <div class="philosophy-heading">
          <p class="eyebrow">1 SỐ SẢN PHẨM ĐẾN TỪ LA MUSE</p>
          <h2>Khoác lên mình <em> sự tự tin.</em></h2>
        </div>

        <section class="gallery-section">
          <div class="gallery-heading"></div>

          <!-- CHỈNH NÚT DI CHUYỂN QUA LẠI -->
          <div class="gallery">
            <button
              class="gallery-btn prev"
              id="prevBtn"
              aria-label="Ảnh trước"
            ></button>

            <!-- CHÈN HÌNH -->
            <div class="gallery-viewport">
              <div class="gallery-track" id="galleryTrack">
                <div class="gallery-item">
                  <img src="../picture/hình 9.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 1.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 11.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 2.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 12.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 3.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 14.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 4.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 15.jpg" />
                </div>

                <div class="gallery-item">
                  <img src="../picture/hình 7.png" />
                </div>
              </div>
            </div>

            <!-- NÚT DI CHUYỂN QUA LẠI -->
            <button
              class="gallery-btn next"
              id="nextBtn"
              aria-label="Ảnh tiếp theo"
            >
              &#10095;
            </button>
          </div>

          <div class="gallery-indicators" id="galleryIndicators"></div>
        </section>
      </section>

      <!-- BANNER 4 -->
      <section class="quote-section">
        <p class="eyebrow">FASHION FOR YOUR STORY</p>

        <blockquote>
          “Quần áo không chỉ để mặc ...
          <br />
          mà còn để toả sáng”
        </blockquote>

        <span class="quote-line"></span>

        <p class="quote-caption">Xinh đẹp hơn, tự tin hơn.</p>
      </section>

      <!-- BANNER 5 -->
      <section class="closing section">
        <p class="eyebrow">FASHION FOR YOUR STORY</p>

        <h2>
          Thời trang không phải là xu hướng,
          <br />
          <em>mà là cá tính.</em>
        </h2>

        <p>Hãy để mỗi ngày trôi qua đều mang theo một điều đáng nhớ.</p>
      </section>
    </main>

    <!-- FOOTER -->
    <footer class="site-footer" id="contact">
      <div class="footer-brand">
        <a class="footer-wordmark" href="#top">La Muse</a>
        <p>Thời trang · Nghệ thuật · Cảm xúc</p>
        <p class="footer-motto">Trouvez votre poésie.</p>
        <div class="social-links" aria-label="Mạng xã hội La Muse">
          <a
            href="https://www.instagram.com/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Instagram"
            title="Instagram"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5" />
              <circle cx="12" cy="12" r="4.1" />
              <circle class="social-dot" cx="17.7" cy="6.6" r="1" />
            </svg>
          </a>
          <a
            href="https://www.pinterest.com/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Pinterest"
            title="Pinterest"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path
                class="social-fill"
                d="M12.2 2.8a9.2 9.2 0 0 0-3.35 17.77c-.02-.72 0-1.58.18-2.36l1.3-5.5s-.32-.63-.32-1.55c0-1.45.84-2.54 1.88-2.54.89 0 1.32.67 1.32 1.47 0 .9-.57 2.24-.86 3.48-.25 1.04.52 1.89 1.54 1.89 1.85 0 3.1-2.38 3.1-5.2 0-2.14-1.44-3.74-4.06-3.74-2.96 0-4.8 2.2-4.8 4.66 0 .85.25 1.45.64 1.91.18.22.21.31.14.56l-.2.8c-.07.25-.27.34-.5.25-1.38-.56-2.02-2.07-2.02-3.76 0-2.8 2.36-6.15 7.03-6.15 3.76 0 6.23 2.72 6.23 5.64 0 3.86-2.15 6.75-5.32 6.75-1.06 0-2.06-.57-2.4-1.21l-.7 2.78c-.21.81-.63 1.63-1 2.27a9.2 9.2 0 1 0 2.17-18.02Z"
              />
            </svg>
          </a>
          <a
            href="https://www.facebook.com/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Facebook"
            title="Facebook"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path
                class="social-fill"
                d="M13.7 21v-8.2h2.75l.41-3.2H13.7V7.56c0-.93.26-1.56 1.59-1.56H17V3.14c-.3-.04-1.32-.14-2.51-.14-2.48 0-4.18 1.51-4.18 4.29V9.6H7.5v3.2h2.81V21h3.39Z"
              />
            </svg>
          </a>
          <a
            href="https://www.tiktok.com/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="TikTok"
            title="TikTok"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path
                class="social-fill"
                d="M19.6 8.1a7.1 7.1 0 0 1-4.3-1.45v7.08a5.73 5.73 0 1 1-5.73-5.73c.37 0 .73.04 1.08.11v3.18a2.62 2.62 0 1 0 1.55 2.4V2.5h3.1c.22 2.08 1.88 3.74 4.3 3.94v1.66Z"
              />
            </svg>
          </a>
        </div>
      </div>
      <div class="footer-column">
        <h3>La Muse</h3>
        <a href="#top">Trang chủ</a><a href="#products">Sản phẩm</a
        ><a href="#story">Giới thiệu</a> <a href="#contact">Liên hệ</a>
      </div>
      <div class="footer-column">
        <h3>Hỗ trợ khách hàng</h3>
        <a href="#contact">Hướng dẫn chọn size</a
        ><a href="#contact">Chính sách đổi trả</a
        ><a href="#contact">Vận chuyển & giao hàng</a
        ><a href="#contact">Chính sách bảo mật</a
        ><a href="#contact">Câu hỏi thường gặp</a>
      </div>
      <div class="footer-column contact-column">
        <h3>Showroom & Contact</h3>
        <p>🏠 123 Rue de Paris, Quận 1,<br />TP. Hồ Chí Minh</p>
        <p>☎ +84 123 456 789</p>
        <p>✉ hello@lamuse.vn</p>
        <p>Giờ mở cửa<br />Thứ 2 – Thứ 7: 09:00 – 20:00</p>
      </div>
      <div class="footer-bottom">
        <span>© 2026 La Muse. All rights reserved.</span
        ><span>Conçu avec amour à Việt Nam</span>
      </div>
    </footer>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="../JS/gioithieu.js" defer></script>
    <script src="../JS/script.js" defer></script>
  </body>
</html>
