<?php
// Header dùng chung cho các trang của La Muse.
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#FBF5DD" />
  <meta name="description" content="La Muse — Tisseuse de Rêves. Thời trang nữ mang tinh thần Pháp cổ điển, thơ mộng và tinh tế." />
  <title>La Muse — Tisseuse de Rêves</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&family=Parisienne&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="announcement">Une petite poésie au quotidien — Một chút thơ cho mỗi ngày</div>
  <header class="site-header" id="top">
    <button class="menu-toggle" aria-label="Mở menu" aria-expanded="false">☰</button>
    <nav class="nav nav-left" aria-label="Điều hướng chính">
      <a class="active" href="#top">Trang chủ</a>
      <a href="#products">Sản phẩm</a>
      <a href="#collections">Bộ sưu tập</a>
    </nav>
    <a class="wordmark" href="#top" aria-label="La Muse trang chủ">
      <span class="brand-name">La Muse</span>
      <span class="brand-quote">Fashion for your story</span>
    </a>
    <nav class="nav nav-right" aria-label="Thông tin">
      <a href="#story">Về chúng tôi</a>
      <a href="#contact">Liên hệ</a>
      <button class="icon-button search-toggle" aria-label="Mở tìm kiếm"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.2 4.2"/></svg></button>
      <button class="icon-button" id="cart-button" aria-label="Giỏ hàng">🛒 <span id="cart-count">0</span></button>
      <button class="account-link" id="account-button"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.5-4 3.2-6 7.5-6s7 2 7.5 6"/></svg><span id="account-label">Đăng nhập</span></button>
    </nav>
    <form class="search-panel" id="search-form" role="search">
      <input id="search-input" type="search" placeholder="Tìm một món đồ bạn yêu..." aria-label="Tìm sản phẩm">
      <button type="submit">Tìm kiếm</button>
      <button type="button" class="close-search" aria-label="Đóng tìm kiếm">×</button>
    </form>
  </header>
