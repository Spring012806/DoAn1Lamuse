<?php
// Header dùng chung cho các trang của La Muse.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$lmPageTitle = $lmPageTitle ?? 'La Muse — Tisseuse de Rêves';

// Kiểm tra trạng thái đăng nhập của khách hàng
$lmUserId = (int)($_SESSION['user_id'] ?? 0);
$lmUserName = trim((string)($_SESSION['user_name'] ?? ''));
$lmIsLoggedIn = $lmUserId > 0;

$lmCartCount = 0;
$lmCart = $_SESSION['lm_cart'] ?? [];

if (is_array($lmCart)) {
    foreach ($lmCart as $item) {
        if (is_array($item)) {
            $lmCartCount += max(0, (int)($item['quantity'] ?? 0));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#FBF5DD" />
  <meta name="description" content="La Muse — Tisseuse de Rêves. Thời trang nữ mang tinh thần Pháp cổ điển, thơ mộng và tinh tế." />
  <title><?= htmlspecialchars($lmPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&family=Parisienne&display=swap" rel="stylesheet">
  
  
  
  <link rel="stylesheet" href="../css/style.css" />
  <?php if ($currentPage === "gioithieu.php"): ?>
    <link rel="stylesheet" href="../css/gioithieu.css" />
  <?php endif; ?>
</head>
<body class="<?= $currentPage === "gioithieu.php" ? "lm-about-page" : "" ?>">
  <div class="announcement">Une petite poésie au quotidien — Một chút thơ cho mỗi ngày</div>
  <header class="site-header" id="top">
    <button class="menu-toggle" aria-label="Mở menu" aria-expanded="false">☰</button>
<nav class="nav nav-left" aria-label="Điều hướng chính">
  <a
    href="index.php"
    class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"
  >
    Trang chủ
  </a>

  <a
    href="products.php"
    class="<?= $currentPage === 'products.php' ? 'active' : '' ?>"
  >
    Sản phẩm
  </a>

</nav>
    <a class="wordmark" href="index.php" aria-label="La Muse trang chủ">
      <span class="brand-name">La Muse</span>
      <span class="brand-quote">Fashion for your story</span>
    </a>
    <nav class="nav nav-right" aria-label="Thông tin">
      <a href="gioithieu.php">Về chúng tôi</a>
      <a href="#contact">Liên hệ</a>
      <button class="icon-button search-toggle" aria-label="Mở tìm kiếm"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.2 4.2"/></svg></button>
      <button
        type="button"
        class="icon-button"
        id="cart-button"
        aria-label="Giỏ hàng"
        onclick="window.location.href='cart.php'"
      >
        🛒 <span id="cart-count"><?= $lmCartCount ?></span>
      </button>
      
<a
  class="account-link"
  id="account-button"
  href="<?= $lmIsLoggedIn ? 'account.php' : 'login.php' ?>"
  aria-label="<?= $lmIsLoggedIn ? 'Tài khoản của tôi' : 'Đăng nhập' ?>"
>
  <svg viewBox="0 0 24 24">
    <circle cx="12" cy="8" r="3.5"/>
    <path d="M4.5 20c.5-4 3.2-6 7.5-6s7 2 7.5 6"/>
  </svg>
  <span id="account-label">
    <?= $lmIsLoggedIn
      ? 'Xin chào, ' . htmlspecialchars($lmUserName !== '' ? $lmUserName : 'bạn', ENT_QUOTES, 'UTF-8')
      : 'Đăng nhập' ?>
  </span>
</a>

    </nav>
<form
  class="search-panel"
  id="search-form"
  role="search"
  action="timkiem.php"
  method="GET"
>
  <input
    id="search-input"
    name="q"
    type="search"
    placeholder="Tìm một món đồ bạn yêu..."
    aria-label="Tìm sản phẩm"
    minlength="2"
    maxlength="100"
    required
  >

  <button type="submit">Tìm kiếm</button>

  <button
    type="button"
    class="close-search"
    aria-label="Đóng tìm kiếm"
  >×</button>
</form>
  </header>


