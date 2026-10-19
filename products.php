<?php
// Trang danh sách sản phẩm La Muse — dữ liệu lấy trực tiếp từ MySQL.
require_once __DIR__ . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Lỗi cấu hình: config.php cần tạo kết nối PDO trong biến $pdo.');
}

function lm_h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vnd($amount): string {
    return number_format((float) $amount, 0, ',', '.') . '₫';
}

// Danh mục đang hoạt động, dùng để tạo bộ lọc động.
$categories = $pdo->query("
    SELECT dm.id, dm.ten_danh_muc, dm.slug, COUNT(sp.id) AS total
    FROM danhmuc dm
    LEFT JOIN sanpham sp
      ON sp.danh_muc_id = dm.id
     AND sp.trang_thai = 'dang_ban'
    WHERE dm.trang_thai = 1
    GROUP BY dm.id, dm.ten_danh_muc, dm.slug
    ORDER BY dm.ten_danh_muc
")->fetchAll(PDO::FETCH_ASSOC);

// Sản phẩm đang bán. Giá hiển thị ưu tiên giá khuyến mãi.
$products = $pdo->query("
    SELECT
        sp.id,
        sp.ma_san_pham,
        sp.ten_san_pham AS name,
        sp.slug,
        dm.ten_danh_muc AS category,
        sp.chat_lieu AS material,
        sp.gia_niem_yet AS original_price,
        sp.gia_khuyen_mai AS sale_price,
        COALESCE(sp.gia_khuyen_mai, sp.gia_niem_yet) AS price,
        sp.anh_dai_dien AS image,
        sp.noi_bat,
        sp.ngay_tao
    FROM sanpham sp
    INNER JOIN danhmuc dm ON dm.id = sp.danh_muc_id
    WHERE sp.trang_thai = 'dang_ban'
      AND dm.trang_thai = 1
    ORDER BY sp.noi_bat DESC, sp.ngay_tao DESC, sp.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Lấy tất cả biến thể cho các sản phẩm, để màu, size và tồn kho phản ánh DB.
$variantsByProduct = [];
$allColors = [];
$allSizes = [];
$materials = [];

if ($products) {
    $ids = array_map('intval', array_column($products, 'id'));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT
            bt.id AS variant_id,
            bt.san_pham_id,
            bt.sku,
            bt.gia_ban,
            bt.so_luong_ton,
            ms.id AS color_id,
            ms.ten_mau AS color_name,
            ms.ma_hex AS color_hex,
            kt.id AS size_id,
            kt.ten_size AS size_name,
            kt.thu_tu
        FROM bien_the_sanpham bt
        INNER JOIN mausac ms
            ON ms.id = bt.mau_sac_id AND ms.trang_thai = 1
        INNER JOIN kichthuoc kt
            ON kt.id = bt.kich_thuoc_id AND kt.trang_thai = 1
        WHERE bt.trang_thai = 1
          AND bt.san_pham_id IN ($marks)
        ORDER BY ms.ten_mau, kt.thu_tu, kt.ten_size
    ");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $pid = (int) $v['san_pham_id'];
        $cid = (int) $v['color_id'];
        $sid = (int) $v['size_id'];
        $qty = (int) $v['so_luong_ton'];
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v['color_hex'])
            ? $v['color_hex'] : '#d9c7a2';

        if (!isset($variantsByProduct[$pid])) {
            $variantsByProduct[$pid] = ['colors' => [], 'sizes' => [], 'stock' => 0];
        }

        if (!isset($variantsByProduct[$pid]['colors'][$cid])) {
            $variantsByProduct[$pid]['colors'][$cid] = [
                'id' => $cid, 'name' => $v['color_name'], 'hex' => $hex, 'stock' => 0
            ];
        }
        $variantsByProduct[$pid]['colors'][$cid]['stock'] += $qty;

        if (!isset($variantsByProduct[$pid]['sizes'][$sid])) {
            $variantsByProduct[$pid]['sizes'][$sid] = [
                'id' => $sid, 'name' => $v['size_name'], 'stock' => 0
            ];
        }
        $variantsByProduct[$pid]['sizes'][$sid]['stock'] += $qty;
        $variantsByProduct[$pid]['stock'] += $qty;

        $allColors[$cid] = ['name' => $v['color_name'], 'hex' => $hex];
        $allSizes[$sid] = ['name' => $v['size_name'], 'order' => (int) $v['thu_tu']];
    }
}

foreach ($products as &$p) {
    $pid = (int) $p['id'];
    $p['colors'] = array_values($variantsByProduct[$pid]['colors'] ?? []);
    $p['sizes'] = array_values($variantsByProduct[$pid]['sizes'] ?? []);
    $p['stock'] = (int) ($variantsByProduct[$pid]['stock'] ?? 0);
    $p['color_names'] = array_column($p['colors'], 'name');
    $p['size_names'] = array_column($p['sizes'], 'name');
    $p['sale_price'] = ($p['sale_price'] !== null && (float) $p['sale_price'] > 0
        && (float) $p['sale_price'] < (float) $p['original_price'])
        ? (float) $p['sale_price'] : null;
    $p['price'] = $p['sale_price'] ?? (float) $p['original_price'];

    $mat = trim((string) ($p['material'] ?? ''));
    if ($mat !== '') $materials[$mat] = $mat;
}
unset($p);

uasort($allSizes, function ($a, $b) {
    if ($a['order'] === $b['order']) return strnatcasecmp($a['name'], $b['name']);
    return $a['order'] <=> $b['order'];
});

$pageTitle = 'Sản phẩm | La Muse';
include __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="products.css">

<main class="lm-products-page">
  <section class="lm-shop-hero">
    <div class="lm-shop-hero__image" role="img" aria-label="Nàng thơ giữa khu vườn Paris ngập nắng"></div>
    <div class="lm-shop-hero__veil"></div>
    <div class="lm-shop-hero__copy">
      <span class="lm-eyebrow">LA NOUVELLE COLLECTION</span>
      <h1>Những điều dịu dàng</h1>
      <p>Dành cho nàng thơ của riêng bạn</p>
      <a href="#lm-catalog" class="lm-button lm-button--light">Khám phá bộ sưu tập <span>↗</span></a>
    </div>
    <span class="lm-hero-note">PRINTEMPS À PARIS · LA MUSE</span>
  </section>

  <div class="lm-breadcrumb"><a href="index.php">Trang chủ</a><span>›</span><span>Sản phẩm</span></div>

  <section class="lm-catalog" id="lm-catalog">
    <aside class="lm-filter" id="lm-filter">
      <div class="lm-filter__heading"><span class="lm-flower">✧</span><h2>Lọc sản phẩm</h2><button type="button" class="lm-filter-close" aria-label="Đóng bộ lọc">×</button></div>

      <div class="lm-filter-group">
        <button class="lm-filter-title" type="button" aria-expanded="true">Danh mục <span>−</span></button>
        <div class="lm-filter-options">
          <label class="lm-check-row"><input type="radio" name="category" value="all" checked><span>Tất cả sản phẩm</span><small><?= count($products) ?></small></label>
          <?php foreach ($categories as $cat): ?>
            <label class="lm-check-row">
              <input type="radio" name="category" value="<?= lm_h($cat['ten_danh_muc']) ?>">
              <span><?= lm_h($cat['ten_danh_muc']) ?></span><small><?= (int) $cat['total'] ?></small>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lm-filter-group">
        <button class="lm-filter-title" type="button" aria-expanded="true">Mức giá <span>−</span></button>
        <div class="lm-filter-options lm-price-filter">
          <label><input type="checkbox" name="price" value="under"> Dưới 1.000.000₫</label>
          <label><input type="checkbox" name="price" value="1to2"> 1.000.000₫ – 2.000.000₫</label>
          <label><input type="checkbox" name="price" value="2to3"> 2.000.000₫ – 3.000.000₫</label>
          <label><input type="checkbox" name="price" value="over"> Trên 3.000.000₫</label>
        </div>
      </div>

      <div class="lm-filter-group">
        <button class="lm-filter-title" type="button" aria-expanded="true">Kích cỡ <span>−</span></button>
        <div class="lm-size-options">
          <?php foreach ($allSizes as $size): ?>
            <button type="button" class="lm-size-chip" data-size="<?= lm_h($size['name']) ?>"><?= lm_h($size['name']) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lm-filter-group">
        <button class="lm-filter-title" type="button" aria-expanded="true">Màu sắc <span>−</span></button>
        <div class="lm-swatches">
          <?php foreach ($allColors as $color): ?>
            <button type="button" class="lm-swatch" style="--swatch:<?= lm_h($color['hex']) ?>"
              aria-label="<?= lm_h($color['name']) ?>" title="<?= lm_h($color['name']) ?>"
              data-color="<?= lm_h($color['name']) ?>"></button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lm-filter-group">
        <button class="lm-filter-title" type="button" aria-expanded="true">Chất liệu <span>−</span></button>
        <div class="lm-filter-options lm-material-filter">
          <?php foreach ($materials as $material): ?>
            <label><input type="checkbox" name="material" value="<?= lm_h($material) ?>"> <?= lm_h($material) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" id="lm-apply-filters" class="lm-button lm-button--green lm-filter-submit">Lọc kết quả <span>⌕</span></button>
      <button type="button" id="lm-reset-filters" class="lm-reset">Xóa tất cả bộ lọc</button>
      <div class="lm-filter-quote"><span>˚˖𓍢ִ໋❀</span><p>La mode se démode,<br>le style jamais.</p></div>
    </aside>

    <div class="lm-products-content">
      <div class="lm-results-toolbar">
        <div><p class="lm-eyebrow">LE BOUTIQUE</p><h2>Tất cả sản phẩm</h2><p class="lm-result-count">Hiển thị <strong id="lm-count"><?= count($products) ?></strong> sản phẩm tuyển chọn</p></div>
        <div class="lm-toolbar-actions">
          <button type="button" class="lm-mobile-filter" id="lm-mobile-filter">☷ Bộ lọc</button>
          <label class="lm-sort">Sắp xếp:
            <select id="lm-sort">
              <option value="popular">Nổi bật trước</option><option value="newest">Mới nhất</option>
              <option value="price-asc">Giá tăng dần</option><option value="price-desc">Giá giảm dần</option><option value="name">Tên A–Z</option>
            </select>
          </label>
          <div class="lm-view-switch" aria-label="Kiểu hiển thị"><button type="button" class="active" data-view="grid" aria-label="Dạng lưới">▦</button><button type="button" data-view="list" aria-label="Dạng danh sách">☷</button></div>
        </div>
      </div>

      <div class="lm-active-filters" id="lm-active-filters"></div>
      <div class="lm-product-grid" id="lm-product-grid">
        <?php foreach ($products as $p): ?>
          <?php
            $name = (string) $p['name'];
            $image = trim((string) ($p['image'] ?? ''));
            $hasSale = $p['sale_price'] !== null;
            $badge = $hasSale ? 'Giảm giá' : ((int) $p['noi_bat'] === 1 ? 'Nổi bật' : '');
            $colorNames = implode(',', $p['color_names']);
            $sizeNames = implode(',', $p['size_names']);
          ?>
          <article class="lm-product-card"
            data-id="<?= (int) $p['id'] ?>"
            data-name="<?= lm_h($name) ?>"
            data-category="<?= lm_h($p['category']) ?>"
            data-material="<?= lm_h($p['material'] ?? '') ?>"
            data-price="<?= (float) $p['price'] ?>"
            data-badge="<?= lm_h($badge) ?>"
            data-color="<?= lm_h($colorNames) ?>"
            data-size="<?= lm_h($sizeNames) ?>"
            data-stock="<?= (int) $p['stock'] ?>">
            <div class="lm-product-media">
              <a href="chitietsanpham.php?id=<?= (int) $p['id'] ?>" class="lm-product-image">
                <?php if ($image !== ''): ?>
                  <img src="<?= lm_h($image) ?>" alt="<?= lm_h($name) ?>" loading="lazy">
                  <img class="lm-product-image-hover" src="<?= lm_h($image) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <div class="lm-product-image-placeholder" role="img" aria-label="Chưa có ảnh sản phẩm">La Muse</div>
                <?php endif; ?>
              </a>
              <?php if ($badge !== ''): ?><span class="lm-product-badge <?= $hasSale ? 'is-sale' : '' ?>"><?= lm_h($badge) ?></span><?php endif; ?>
              <button class="lm-wishlist" type="button" aria-label="Thêm vào yêu thích" aria-pressed="false">♡</button>
              <div class="lm-quick-actions">
                <a href="chitietsanpham.php?id=<?= (int) $p['id'] ?>" class="lm-quick-view">Xem chi tiết</a>
                
              </div>
            </div>
            <div class="lm-product-info">
              <a href="chitietsanpham.php?id=<?= (int) $p['id'] ?>" class="lm-product-name"><?= lm_h($name) ?></a>
              <p class="lm-product-material"><?= lm_h($p['material'] ?? '') ?></p>
              <div class="lm-price-line">
                <span class="lm-price <?= $hasSale ? 'is-sale' : '' ?>"><?= vnd($p['price']) ?></span>
                <?php if ($hasSale): ?><del><?= vnd($p['original_price']) ?></del><?php endif; ?>
              </div>
              <div class="lm-product-bottom">
                <span class="lm-rating"><?php if ($p['stock'] > 0): ?><b>●</b> <small>Còn <?= (int) $p['stock'] ?> sản phẩm</small><?php else: ?><small>Hết hàng</small><?php endif; ?></span>
                <div class="lm-product-colors" aria-label="Màu sắc sản phẩm">
                  <?php foreach ($p['colors'] as $color): ?>
                    <i style="--swatch:<?= lm_h($color['hex']) ?>" title="<?= lm_h($color['name']) ?>" aria-label="<?= lm_h($color['name']) ?>"></i>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="lm-empty-state" id="lm-empty-state" <?= $products ? 'hidden' : '' ?>>
        <span>❧</span><h3><?= $products ? 'Chưa tìm thấy nàng thơ phù hợp' : 'La Muse đang chuẩn bị những thiết kế đầu tiên' ?></h3>
        <p><?= $products ? 'Hãy thử thay đổi bộ lọc để khám phá thêm nhé.' : 'Thêm sản phẩm đang bán vào database để chúng xuất hiện tại đây.' ?></p>
        <button type="button" class="lm-button lm-button--green" id="lm-empty-reset">Xem tất cả sản phẩm</button>
      </div>
      <nav class="lm-pagination" aria-label="Phân trang"><button type="button" disabled aria-label="Trang trước">‹</button><button type="button" class="active" aria-current="page">1</button><button type="button" disabled aria-label="Trang sau">›</button></nav>
    </div>
  </section>
</main>

<script src="products.js"></script>
<?php include __DIR__ . '/footer.php'; ?>
