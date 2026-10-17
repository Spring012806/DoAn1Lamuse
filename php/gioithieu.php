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
    <link rel="stylesheet" href="css/footer.css" />
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
    <?php include 'header.php'; ?>
    
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

    
    <script src="../JS/gioithieu.js" defer></script>
    <script src="../JS/script.js" defer></script>
    <?php include 'footer.php'; ?>
  </body>
</html>
