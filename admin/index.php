<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$pageTitle = 'Tổng quan'; $active = 'dashboard';
$stats = [];
$stats['products'] = (int)$pdo->query("SELECT COUNT(*) FROM sanpham")->fetchColumn();
$stats['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM danhmuc")->fetchColumn();
$stats['variants'] = (int)$pdo->query("SELECT COUNT(*) FROM bien_the_sanpham")->fetchColumn();
$stats['stock'] = (int)$pdo->query("SELECT COALESCE(SUM(so_luong_ton),0) FROM bien_the_sanpham")->fetchColumn();
$lowStock = $pdo->query("SELECT b.id,b.sku,b.so_luong_ton,p.ten_san_pham,m.ten_mau,k.ten_size FROM bien_the_sanpham b JOIN sanpham p ON p.id=b.san_pham_id LEFT JOIN mausac m ON m.id=b.mau_sac_id LEFT JOIN kichthuoc k ON k.id=b.kich_thuoc_id WHERE b.so_luong_ton <= 5 ORDER BY b.so_luong_ton ASC LIMIT 8")->fetchAll();
require __DIR__ . '/admin-header.php';
?>
<div class="stats">
 <div class="stat"><span>TỔNG SẢN PHẨM</span><strong><?= number_format($stats['products']) ?></strong><small>Trong danh mục cửa hàng</small></div>
 <div class="stat"><span>DANH MỤC</span><strong><?= number_format($stats['categories']) ?></strong><small>Nhóm sản phẩm</small></div>
 <div class="stat"><span>BIẾN THỂ</span><strong><?= number_format($stats['variants']) ?></strong><small>Màu sắc và kích thước</small></div>
 <div class="stat"><span>TỔNG TỒN KHO</span><strong><?= number_format($stats['stock']) ?></strong><small>Đơn vị sản phẩm</small></div>
</div>
<section class="panel"><div class="panel-head"><div><h2>Kiểm tra tồn kho</h2><div class="muted">Các biến thể còn 5 sản phẩm trở xuống.</div></div><a class="btn" href="products.php">Quản lý sản phẩm</a></div>
<div class="table-wrap"><table><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Màu</th><th>Size</th><th>Tồn kho</th></tr></thead><tbody>
<?php if (!$lowStock): ?><tr><td colspan="5" class="empty">Chưa có biến thể cần lưu ý.</td></tr><?php endif; ?>
<?php foreach ($lowStock as $r): ?><tr><td><?= e($r['sku']) ?></td><td><?= e($r['ten_san_pham']) ?></td><td><?= e($r['ten_mau'] ?? '—') ?></td><td><?= e($r['ten_size'] ?? '—') ?></td><td><span class="badge <?= (int)$r['so_luong_ton']===0?'off':'' ?>"><?= (int)$r['so_luong_ton'] ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Lối tắt</h2><div class="muted">Các thao tác quản trị thường dùng.</div></div></div><div class="actions"><a class="btn" href="product-form.php">＋ Thêm sản phẩm</a><a class="btn light" href="categories.php">Danh mục</a><a class="btn light" href="variants.php">Tồn kho & biến thể</a><a class="btn light" href="colors.php">Thêm màu</a><a class="btn light" href="sizes.php">Thêm size</a></div></section>
<?php require __DIR__ . '/admin-footer.php'; ?>
