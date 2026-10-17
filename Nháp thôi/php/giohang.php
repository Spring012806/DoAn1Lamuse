<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="La Muse — Tisseuse de Rêves. Thời trang nữ mang tinh thần Pháp cổ điển, thơ mộng và tinh tế."
    >

    <title>Giỏ hàng | La Muse</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Agbalumo&family=Alice&family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Pacifico&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Playwrite+AR:wght@100..400&family=Playwrite+CA+Guides&family=Playwrite+CU+Guides&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/giohang.css">
    <link rel="stylesheet" href="../css/footer.css">
</head>


  <body>
    <?php include 'header.php'; ?>

    

    <!-- NỘI DUNG CHÍNH -->
    <main>
      <!-- BANNER -->
      <section class="cart-banner">
        <div class="banner-overlay"></div>

        <div class="banner-content">
          <p class="banner-eyebrow">LA MUSE — TISSEUSE DE RÊVES</p>

          <h1>Giỏ hàng của quý cô</h1>

          <p class="banner-description">
            Những lựa chọn tinh tế dành riêng cho quý cô.
          </p>
        </div>
      </section>

      <!-- GIỎ HÀNG TRỐNG -->
      <section class="empty-cart">
        <!-- ICON TÚI MUA SẮM -->
        <div class="empty-cart-icon">
          <svg viewBox="0 0 80 80" aria-hidden="true">
            <path d="M22 30 H58 L54 68 H26 Z"></path>
            <path d="M30 31 V22 A10 10 0 0 1 50 22 V31"></path>
          </svg>
        </div>

        <h2>Giỏ hàng đang chờ bạn đấy!</h2>

        <p class="empty-description">
          Có vẻ như bạn vẫn chưa chọn được món đồ yêu thích. Hãy khám phá bộ sưu
          tập của La Muse và tìm thấy thiết kế dành riêng cho mình.
        </p>

        <!-- GẮN LINK TRANG SẢN PHẨM DÔ ĐÂY -->
        <a href="sanpham.html" class="shop-button">
          <span>TIẾP TỤC MUA SẮM</span>
          <span class="button-arrow">⟶</span>
        </a>

        <p class="bottom-note">FASHION FOR YOUR STORY</p>
      </section>
    </main>

    
    <?php include 'footer.php'; ?>
  </body>
</html>
