<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function lm_h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function lm_vnd($value): string {
    return number_format((float)$value, 0, ',', '.') . '₫';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit('Sản phẩm không hợp lệ.');
}

$stmt = $pdo->prepare("
    SELECT sp.*, dm.ten_danh_muc
    FROM sanpham sp
    INNER JOIN danhmuc dm ON dm.id = sp.danh_muc_id
    WHERE sp.id = :id
      AND sp.trang_thai = 'dang_ban'
      AND dm.trang_thai = 1
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    http_response_code(404);
    exit('Không tìm thấy sản phẩm.');
}

$stmt = $pdo->prepare("
    SELECT duong_dan, mo_ta_anh, thu_tu, anh_chinh
    FROM hinhanh_sanpham
    WHERE san_pham_id = :id
    ORDER BY anh_chinh DESC, thu_tu ASC, id ASC
");
$stmt->execute(['id' => $id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cover = trim((string)($product['anh_dai_dien'] ?? ''));
if ($cover !== '') {
    $found = false;
    foreach ($images as $img) {
        if (($img['duong_dan'] ?? '') === $cover) { $found = true; break; }
    }
    if (!$found) {
        array_unshift($images, [
            'duong_dan' => $cover,
            'mo_ta_anh' => $product['ten_san_pham'],
            'thu_tu' => 0,
            'anh_chinh' => 1
        ]);
    }
}

$stmt = $pdo->prepare("
    SELECT bt.id AS variant_id, bt.sku, bt.gia_ban, bt.so_luong_ton,
           ms.id AS color_id, ms.ten_mau, ms.ma_hex,
           kt.id AS size_id, kt.ten_size, kt.thu_tu
    FROM bien_the_sanpham bt
    INNER JOIN mausac ms ON ms.id = bt.mau_sac_id
    INNER JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
    WHERE bt.san_pham_id = :id
      AND bt.trang_thai = 1 AND ms.trang_thai = 1 AND kt.trang_thai = 1
    ORDER BY ms.id ASC, kt.thu_tu ASC, kt.id ASC
");
$stmt->execute(['id' => $id]);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$listPrice = (float)$product['gia_niem_yet'];
$sale = $product['gia_khuyen_mai'];
$displayPrice = ($sale !== null && (float)$sale > 0 && (float)$sale < $listPrice)
    ? (float)$sale : $listPrice;

$mainImage = $images[0]['duong_dan'] ?? '';
$wishlisted = in_array((int)$product['id'], $_SESSION['lm_wishlist'] ?? [], true);

$jsVariants = array_map(static function (array $v): array {
    return [
        'id' => (int)$v['variant_id'],
        'sku' => $v['sku'],
        'price' => ($v['gia_ban'] !== null && (float)$v['gia_ban'] > 0) ? (float)$v['gia_ban'] : null,
        'stock' => (int)$v['so_luong_ton'],
        'colorId' => (int)$v['color_id'],
        'colorName' => $v['ten_mau'],
        'hex' => $v['ma_hex'] ?: '#E5E0D6',
        'sizeId' => (int)$v['size_id'],
        'sizeName' => $v['ten_size'],
        'sizeOrder' => (int)$v['thu_tu']
    ];
}, $variants);

require_once __DIR__ . '/header.php';
?>


<link rel="stylesheet" href="../css/chitietsanpham.css">

<main class="lm-page">
  <nav class="lm-crumb" aria-label="Breadcrumb">
    <a href="index.php">Trang chủ</a><span>/</span>
    <a href="products.php">Sản phẩm</a><span>/</span>
    <span><?= lm_h($product['ten_san_pham']) ?></span>
  </nav>

  <div class="lm-product-layout">
    <section class="lm-gallery" aria-label="Hình ảnh sản phẩm">
      <div class="lm-hero-image">
        <?php if ($mainImage !== ''): ?>
          <img id="lmMainImage" src="<?= lm_h($mainImage) ?>" alt="<?= lm_h($images[0]['mo_ta_anh'] ?? $product['ten_san_pham']) ?>">
        <?php else: ?>
          <div class="lm-image-placeholder">La Muse</div>
        <?php endif; ?>
        <?php if ((int)$product['noi_bat'] === 1): ?><span class="lm-editor-pick">La Muse sélection</span><?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
      <div class="lm-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <button type="button" class="lm-thumb <?= $i === 0 ? 'is-active' : '' ?>"
            data-image="<?= lm_h($img['duong_dan']) ?>"
            data-alt="<?= lm_h($img['mo_ta_anh'] ?? $product['ten_san_pham']) ?>"
            aria-label="Xem ảnh <?= $i + 1 ?>">
            <img src="<?= lm_h($img['duong_dan']) ?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="lm-info">
      <p class="lm-eyebrow"><?= lm_h($product['ten_danh_muc']) ?> · LA MUSE</p>
      <h1><?= lm_h($product['ten_san_pham']) ?></h1>
      <div class="lm-rating" aria-label="Chưa có dữ liệu đánh giá">
        <span class="lm-rating-stars" aria-hidden="true">☆☆☆☆☆</span>
        <span>Chưa có đánh giá</span>
      </div>

      <div class="lm-price-row">
        <span id="lmPrice" class="lm-price"><?= lm_vnd($displayPrice) ?></span>
        <?php if ($sale !== null && (float)$sale > 0 && (float)$sale < $listPrice): ?>
          <del class="lm-old-price"><?= lm_vnd($listPrice) ?></del>
          <span class="lm-sale-tag">Ưu đãi</span>
        <?php endif; ?>
      </div>
      <p class="lm-sku">Mã sản phẩm: <?= lm_h($product['ma_san_pham']) ?></p>
      <?php if (!empty($product['mo_ta'])): ?>
        <p class="lm-description"><?= nl2br(lm_h($product['mo_ta'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($product['chat_lieu'])): ?>
        <p class="lm-material"><span>Chất liệu</span><strong><?= lm_h($product['chat_lieu']) ?></strong></p>
      <?php endif; ?>

      <?php if ($variants): ?>
      <div class="lm-choice-block">
        <div class="lm-choice-heading"><span>Màu sắc</span><strong id="lmColorLabel">Chọn màu</strong></div>
        <div id="lmColors" class="lm-color-list"></div>
      </div>

      <div class="lm-choice-block">
        <div class="lm-choice-heading">
          <span>Kích thước</span>
          <button type="button" class="lm-text-link" id="lmOpenSizeGuide">Hướng dẫn chọn size ↗</button>
        </div>
        <div id="lmSizes" class="lm-size-list"></div>
      </div>

      <div id="lmAvailability" class="lm-availability" aria-live="polite">Vui lòng chọn màu sắc và kích thước.</div>

      <div class="lm-quantity-row">
        <span>Số lượng</span>
        <div class="lm-quantity-control">
          <button type="button" id="lmQtyMinus" aria-label="Giảm số lượng">−</button>
          <input type="number" id="lmQuantity" min="1" value="1" inputmode="numeric" aria-label="Số lượng">
          <button type="button" id="lmQtyPlus" aria-label="Tăng số lượng">+</button>
        </div>
      </div>

      <div class="lm-actions">
        <button type="button" id="lmAddCart" class="lm-btn lm-btn-primary" disabled>Thêm vào giỏ hàng</button>
        <button type="button" id="lmBuyNow" class="lm-btn lm-btn-secondary" disabled>Mua ngay</button>
        <button type="button" id="lmWishlist" class="lm-wishlist <?= $wishlisted ? 'is-active' : '' ?>"
          aria-pressed="<?= $wishlisted ? 'true' : 'false' ?>" aria-label="Yêu thích">
          <span aria-hidden="true"><?= $wishlisted ? '♥' : '♡' ?></span>
        </button>
      </div>
      <p id="lmFeedback" class="lm-feedback" role="status" aria-live="polite"></p>
      <?php else: ?>
      <p class="lm-availability is-unavailable">Sản phẩm hiện chưa có biến thể để đặt mua.</p>
      <?php endif; ?>

      <div class="lm-promises">
        <div><span aria-hidden="true">✧</span><p><strong>Chăm chút từng chi tiết</strong><small>Phong cách vượt thời gian</small></p></div>
        <div><span aria-hidden="true">♧</span><p><strong>Hỗ trợ chọn size</strong><small>Liên hệ nếu nàng cần tư vấn</small></p></div>
      </div>
    </section>
  </div>

  <section class="lm-story">
    <p class="lm-eyebrow">L'ESPRIT DE LA MUSE</p>
    <h2>Một nét dịu dàng, một dấu ấn riêng</h2>
    <p><?= nl2br(lm_h($product['mo_ta'] ?? '')) ?></p>
  </section>
</main>

<div class="lm-modal-backdrop" id="lmSizeModal" hidden>
  <section class="lm-size-modal" role="dialog" aria-modal="true" aria-labelledby="lmSizeTitle">
    <button type="button" class="lm-modal-close" id="lmCloseSizeGuide" aria-label="Đóng hướng dẫn">×</button>
    <p class="lm-eyebrow">SIZE GUIDE · LA MUSE</p>
    <h2 id="lmSizeTitle">Tìm chiếc váy vừa vặn</h2>
    <p class="lm-modal-intro">Số đo tham khảo (cm)</p>
    <div class="lm-size-table-wrap">
      <table class="lm-size-table">
        <thead><tr><th>Size</th><th>Vòng ngực</th><th>Vòng eo</th><th>Vòng mông</th></tr></thead>
        <tbody>
          <tr><th scope="row">S</th><td>82–86</td><td>64–68</td><td>88–92</td></tr>
          <tr><th scope="row">M</th><td>86–90</td><td>68–72</td><td>92–96</td></tr>
          <tr><th scope="row">L</th><td>90–94</td><td>72–76</td><td>96–100</td></tr>
        </tbody>
      </table>
    </div>
    <p class="lm-size-note">Nàng hãy đo sát cơ thể bằng thước dây mềm. Nếu số đo nằm giữa hai size, có thể cân nhắc size lớn hơn để thoải mái hơn.</p>
    <p class="lm-size-disclaimer">Lưu ý: đây là bảng tham khảo mẫu; hãy xác nhận thông số thực tế của từng thiết kế trước khi công bố bán hàng.</p>
  </section>
</div>

<script>
window.LM_PRODUCT = <?= json_encode([
    'id' => (int)$product['id'],
    'name' => $product['ten_san_pham'],
    'price' => $displayPrice
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.LM_VARIANTS = <?= json_encode($jsVariants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>


<script src="chitietsanpham.js" defer></script>
<?php require_once __DIR__ . '/footer.php'; ?>
