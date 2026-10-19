<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$pageTitle = $pageTitle ?? 'Quản trị';
$active = $active ?? '';
$flashMessage = get_flash();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · La Muse Admin</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-shell">
<aside class="sidebar">
  <a class="brand" href="index.php"><span class="brand-mark">LM</span><span>La Muse<small>ESPACE ADMIN</small></span></a>
  <div class="side-label">QUẢN LÝ CỬA HÀNG</div>
<nav>
    <a class="<?= $active==='dashboard'?'active':'' ?>" href="index.php">◫ <span>Tổng quan</span></a>
    <a class="<?= $active==='orders'?'active':'' ?>" href="orders.php">▤ <span>Đơn hàng</span></a>
    <a class="<?= $active==='products'?'active':'' ?>" href="products.php">◇ <span>Sản phẩm</span></a>
    <a class="<?= $active==='categories'?'active':'' ?>" href="categories.php">▤ <span>Danh mục</span></a>
    <a class="<?= $active==='colors'?'active':'' ?>" href="colors.php">◉ <span>Màu sắc</span></a>
    <a class="<?= $active==='sizes'?'active':'' ?>" href="sizes.php">↔ <span>Kích thước (size)</span></a>
    <a class="<?= $active==='users'?'active':'' ?>" href="users.php">♙ <span>Khách hàng</span></a>
</nav>
  <div class="sidebar-bottom"><span>Quản trị viên</span><a href="logout.php">Đăng xuất ↗</a></div>
</aside>
<main class="main">
<header class="topbar"><div><span class="eyebrow">LA MUSE / ADMINISTRATION</span><h1><?= e($pageTitle) ?></h1></div><a class="profile" href="logout.php">Đăng xuất <span>↗</span></a></header>
<?php if ($flashMessage): ?><div class="alert <?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
