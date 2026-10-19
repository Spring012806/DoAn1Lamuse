
<?php
require_once __DIR__ . '/config.php';

function search_h($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function search_vnd($value): string {
    return number_format((float)$value, 0, ',', '.') . '₫';
}

$q = trim((string)($_GET['q'] ?? ''));
$products = [];
$error = '';

if (mb_strlen($q, 'UTF-8') > 100) {
    $q = mb_substr($q, 0, 100, 'UTF-8');
}

if ($q !== '' && mb_strlen($q, 'UTF-8') >= 2) {
    $keyword = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT
            sp.id,
            sp.ten_san_pham,
            sp.ma_san_pham,
            sp.slug,
            sp.gia_niem_yet,
            sp.gia_khuyen_mai,
            sp.anh_dai_dien,
            dm.ten_danh_muc
        FROM sanpham sp
        INNER JOIN danhmuc dm
            ON dm.id = sp.danh_muc_id
        WHERE sp.trang_thai = 'dang_ban'
          AND dm.trang_thai = 1
          AND (
              sp.ten_san_pham LIKE :name
              OR sp.ma_san_pham LIKE :code
              OR sp.slug LIKE :slug
              OR dm.ten_danh_muc LIKE :category
          )
        ORDER BY sp.noi_bat DESC, sp.ngay_tao DESC
        LIMIT 60
    ");

    $stmt->execute([
        'name' => $keyword,
        'code' => $keyword,
        'slug' => $keyword,
        'category' => $keyword,
    ]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/header.php';
?>

<style>
.search-results-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 38px 24px 70px;
    color: #293427;
}
.search-results-heading {
    margin: 0 0 10px;
    font-family: "Cormorant Garamond", Georgia, serif;
    font-size: clamp(32px, 5vw, 46px);
    font-weight: 500;
}
.search-results-summary {
    margin-bottom: 28px;
    color: #77776a;
    font-size: 14px;
}
.search-results-summary strong {
    color: #306d29;
}
.search-results-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 28px 20px;
}
.search-product {
    min-width: 0;
    color: inherit;
    text-decoration: none;
}
.search-product-image {
    overflow: hidden;
    aspect-ratio: 4 / 5;
    background: #f2ecdc;
}
.search-product-image img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    transition: transform .35s ease;
}
.search-product:hover img {
    transform: scale(1.035);
}
.search-product-category {
    margin: 13px 0 6px;
    color: #77776a;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.search-product-name {
    margin: 0 0 8px;
    font-family: "Cormorant Garamond", Georgia, serif;
    font-size: 23px;
    font-weight: 500;
}
.search-product-price {
    color: #306d29;
    font-size: 14px;
    font-weight: 600;
}
.search-empty {
    padding: 36px 20px;
    background: #fbf5dd;
    text-align: center;
    line-height: 1.8;
}
.search-empty h2 {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-weight: 500;
    font-size: 30px;
}
.search-empty a {
    color: #306d29;
}
@media (max-width: 760px) {
    .search-results-page { padding: 25px 16px 50px; }
    .search-results-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px 12px; }
    .search-product-name { font-size: 19px; }
}
@media (max-width: 380px) {
    .search-results-grid { grid-template-columns: 1fr; }
}
</style>

<main class="search-results-page">
    <p style="color:#306d29;letter-spacing:2px;font-size:11px">
        LA MUSE · DISCOVERY
    </p>

    <h1 class="search-results-heading">Tìm kiếm sản phẩm</h1>

    <?php if ($q === '' || mb_strlen($q, 'UTF-8') < 2): ?>

        <p class="search-results-summary">
            Hãy nhập ít nhất 2 ký tự để tìm thiết kế nàng yêu thích.
        </p>

    <?php else: ?>

        <p class="search-results-summary">
            Từ khóa:
            <strong><?= search_h($q) ?></strong>
            — Tìm thấy <strong><?= count($products) ?></strong> sản phẩm.
        </p>

        <?php if ($products): ?>

            <div class="search-results-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $sale = $product['gia_khuyen_mai'];
                    $listPrice = (float)$product['gia_niem_yet'];

                    $price = (
                        $sale !== null
                        && (float)$sale > 0
                        && (float)$sale < $listPrice
                    ) ? (float)$sale : $listPrice;

                    $image = trim(
                        (string)($product['anh_dai_dien'] ?? '')
                    );
                    ?>

                    <a
                        class="search-product"
                        href="chitietsanpham.php?id=<?= (int)$product['id'] ?>"
                    >
                        <div class="search-product-image">
                            <?php if ($image !== ''): ?>
                                <img
                                    src="<?= search_h($image) ?>"
                                    alt="<?= search_h($product['ten_san_pham']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div style="height:100%;display:grid;place-items:center;color:#306d29">
                                    La Muse
                                </div>
                            <?php endif; ?>
                        </div>

                        <p class="search-product-category">
                            <?= search_h($product['ten_danh_muc']) ?>
                        </p>

                        <h2 class="search-product-name">
                            <?= search_h($product['ten_san_pham']) ?>
                        </h2>

                        <div class="search-product-price">
                            <?= search_vnd($price) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else: ?>

            <div class="search-empty">
                <h2>Chưa tìm thấy món đồ phù hợp</h2>
                <p>
                    Nàng thử một từ khóa khác như “váy”, “hoa” hoặc mã sản phẩm nhé.
                </p>
                <a href="products.php">Khám phá tất cả sản phẩm</a>
            </div>

        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
