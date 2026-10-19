<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function lm_cart_h($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function lm_cart_money($value): string {
    return number_format((float)$value, 0, ',', '.') . '₫';
}

function lm_cart_redirect(): never {
    header('Location: cart.php');
    exit;
}

function lm_cart_flash(string $message, string $type = 'success'): void {
    $_SESSION['lm_cart_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

/* Khởi tạo CSRF token */
if (empty($_SESSION['lm_cart_csrf'])) {
    $_SESSION['lm_cart_csrf'] = bin2hex(random_bytes(32));
}

$cart = $_SESSION['lm_cart'] ?? [];
if (!is_array($cart)) {
    $cart = [];
    $_SESSION['lm_cart'] = [];
}

/* Xử lý cập nhật giỏ hàng phía máy chủ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['lm_cart_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
    }

    $action = $_POST['cart_action'] ?? '';

    if ($action === 'remove') {
        $removeId = filter_var(
            $_POST['variant_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($removeId === false || $removeId < 1) {
            lm_cart_flash('Sản phẩm không hợp lệ.', 'error');
        } elseif (isset($_SESSION['lm_cart'][$removeId])) {
            unset($_SESSION['lm_cart'][$removeId]);
            lm_cart_flash('Đã xóa sản phẩm khỏi giỏ hàng.');
        }

        lm_cart_redirect();
    }

    if ($action === 'update') {
        $postedQuantities = $_POST['quantities'] ?? [];

        if (!is_array($postedQuantities)) {
            lm_cart_flash('Dữ liệu số lượng không hợp lệ.', 'error');
            lm_cart_redirect();
        }

        $cart =& $_SESSION['lm_cart'];
        $messages = [];
        $updated = [];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT
                    bt.id,
                    bt.sku,
                    bt.san_pham_id,
                    bt.so_luong_ton,
                    bt.gia_ban,
                    bt.mau_sac_id,
                    bt.kich_thuoc_id,
                    sp.ten_san_pham,
                    sp.ma_san_pham,
                    sp.gia_niem_yet,
                    sp.gia_khuyen_mai,
                    ms.ten_mau,
                    kt.ten_size
                FROM bien_the_sanpham bt
                JOIN sanpham sp ON sp.id = bt.san_pham_id
                JOIN danhmuc dm ON dm.id = sp.danh_muc_id
                JOIN mausac ms ON ms.id = bt.mau_sac_id
                JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
                WHERE bt.id = :id
                  AND bt.trang_thai = 1
                  AND sp.trang_thai = 'dang_ban'
                  AND dm.trang_thai = 1
                  AND ms.trang_thai = 1
                  AND kt.trang_thai = 1
                LIMIT 1
                FOR UPDATE
            ");

            foreach ($cart as $key => $row) {
                $id = filter_var($key, FILTER_VALIDATE_INT);

                if ($id === false || $id < 1) {
                    unset($cart[$key]);
                    $messages[] = 'Đã loại bỏ một dòng giỏ hàng không hợp lệ.';
                    continue;
                }

                // Nếu dòng này không có trong form thì giữ nguyên số lượng.
                if (!array_key_exists((string)$id, $postedQuantities)
                    && !array_key_exists($id, $postedQuantities)) {
                    continue;
                }

                $rawQty = $postedQuantities[$id]
                    ?? $postedQuantities[(string)$id]
                    ?? null;

                $quantity = filter_var($rawQty, FILTER_VALIDATE_INT);

                if ($quantity === false || $quantity < 1 || $quantity > 99) {
                    $messages[] = 'Số lượng không hợp lệ cho một sản phẩm.';
                    continue;
                }

                $stmt->execute(['id' => $id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$v) {
                    unset($cart[$id]);
                    $messages[] = 'Một sản phẩm đã ngừng bán và được xóa khỏi giỏ.';
                    continue;
                }

                $stock = (int)$v['so_luong_ton'];

                if ($stock < 1) {
                    unset($cart[$id]);
                    $messages[] = $v['ten_san_pham'] . ' đã hết hàng và được xóa khỏi giỏ.';
                    continue;
                }

                if ($quantity > $stock) {
                    $quantity = $stock;
                    $messages[] = 'Số lượng ' . $v['ten_san_pham']
                        . ' được điều chỉnh theo tồn kho hiện tại (' . $stock . ').';
                }

                $price = (
                    $v['gia_ban'] !== null &&
                    (float)$v['gia_ban'] > 0
                )
                    ? (float)$v['gia_ban']
                    : (
                        $v['gia_khuyen_mai'] !== null &&
                        (float)$v['gia_khuyen_mai'] > 0 &&
                        (float)$v['gia_khuyen_mai'] < (float)$v['gia_niem_yet']
                            ? (float)$v['gia_khuyen_mai']
                            : (float)$v['gia_niem_yet']
                    );

                $updated[$id] = [
                    'variant_id' => (int)$v['id'],
                    'product_id' => (int)$v['san_pham_id'],
                    'sku' => $v['sku'],
                    'name' => $v['ten_san_pham'],
                    'product_code' => $v['ma_san_pham'],
                    'color_id' => (int)$v['mau_sac_id'],
                    'color_name' => $v['ten_mau'],
                    'size_id' => (int)$v['kich_thuoc_id'],
                    'size_name' => $v['ten_size'],
                    'price' => $price,
                    'quantity' => $quantity,
                ];
            }

            foreach ($updated as $id => $row) {
                $cart[$id] = $row;
            }

            $pdo->commit();

            if ($messages) {
                lm_cart_flash(implode(' ', $messages), 'error');
            } else {
                lm_cart_flash('Giỏ hàng đã được cập nhật.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            lm_cart_flash(
                'Chưa thể cập nhật giỏ hàng. Con vui lòng thử lại.',
                'error'
            );
        }

        lm_cart_redirect();
    }

    lm_cart_flash('Thao tác không hợp lệ.', 'error');
    lm_cart_redirect();
}

/* Đọc lại giỏ sau khi xử lý POST */

$cart = $_SESSION['lm_cart'] ?? [];
$flash = $_SESSION['lm_cart_flash'] ?? null;
unset($_SESSION['lm_cart_flash']);

// Bổ sung ảnh sản phẩm cho các dòng giỏ hàng cũ.
$productIds = [];

foreach ($cart as $row) {
    $pid = (int)($row['product_id'] ?? 0);
    if ($pid > 0) {
        $productIds[] = $pid;
    }
}

$productIds = array_values(array_unique($productIds));

if ($productIds) {
    $marks = implode(',', array_fill(0, count($productIds), '?'));
    $imageStmt = $pdo->prepare(
        "SELECT id, anh_dai_dien
         FROM sanpham
         WHERE id IN ($marks)"
    );
    $imageStmt->execute($productIds);

    $productImages = [];
    foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $productImages[(int)$item['id']] = $item['anh_dai_dien'];
    }

    foreach ($cart as &$row) {
        $pid = (int)($row['product_id'] ?? 0);

        if (empty($row['image']) && isset($productImages[$pid])) {
            $row['image'] = $productImages[$pid];
        }
    }
    unset($row);

    $_SESSION['lm_cart'] = $cart;
}


$total = 0;
$count = 0;

foreach ($cart as $row) {
    $quantity = max(0, (int)($row['quantity'] ?? 0));
    $price = max(0, (float)($row['price'] ?? 0));

    $total += $price * $quantity;
    $count += $quantity;
}

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="chitietsanpham.css">
<link rel="stylesheet" href="cart.css">

<main class="lm-page lm-cart-page">
    <nav class="lm-crumb">
        <a href="index.php">Trang chủ</a>
        <span>/</span>
        <span>Giỏ hàng</span>
    </nav>

    <p class="lm-eyebrow">VOTRE SÉLECTION</p>
    <h1 class="lm-cart-title">Giỏ hàng của nàng</h1>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="lm-cart-alert <?= ($flash['type'] ?? '') === 'error' ? 'is-error' : '' ?>"
             role="status">
            <?= lm_cart_h($flash['message']) ?>
        </div>
    <?php endif; ?>


<?php if (!$cart): ?>
    <div class="lm-cart-empty">
        <span class="empty-flower">✧</span>
        <h2>Một khoảng trống dịu dàng</h2>
        <p>
            Giỏ hàng của nàng vẫn đang chờ những thiết kế yêu thích.<br>
            Hãy cùng La Muse tìm món đồ dành riêng cho câu chuyện của nàng.
        </p>
        <a class="lm-btn lm-btn-primary"
           href="products.php"
           style="display:inline-flex;align-items:center;text-decoration:none">
            Khám phá bộ sưu tập
        </a>
    </div>
<?php else: ?>

        <form method="post" action="cart.php" id="lmCartForm">
            <input type="hidden" name="csrf_token"
                   value="<?= lm_cart_h($_SESSION['lm_cart_csrf']) ?>">
            <input type="hidden" name="cart_action" value="update">

            <div class="lm-cart-table-wrap">
                <table class="lm-cart-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Phân loại</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Tạm tính</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart as $key => $row):
                        $id = (int)$key;
                        $quantity = max(1, (int)($row['quantity'] ?? 1));
                        $price = max(0, (float)($row['price'] ?? 0));
                    ?>
                        <tr>
                            <td class="lm-cart-product">
                                <div class="lm-cart-product-card">
                                    <a class="lm-cart-thumb"
                                      href="chitietsanpham.php?id=<?= (int)($row['product_id'] ?? 0) ?>">
                                        <?php if (!empty($row['image'])): ?>
                                            <img
                                                src="<?= lm_cart_h($row['image']) ?>"
                                                alt="<?= lm_cart_h($row['name'] ?? 'Sản phẩm La Muse') ?>"
                                                loading="lazy">
                                        <?php else: ?>
                                            <span class="lm-cart-thumb-placeholder">La Muse</span>
                                        <?php endif; ?>
                                    </a>

                                    <div class="lm-cart-product-details">
                                        <a class="lm-cart-product-name"
                                          href="chitietsanpham.php?id=<?= (int)($row['product_id'] ?? 0) ?>">
                                            <?= lm_cart_h($row['name'] ?? 'Sản phẩm') ?>
                                        </a>
                                        <small><?= lm_cart_h($row['sku'] ?? '') ?></small>
                                        <span class="lm-cart-variant">
                                            <?= lm_cart_h($row['color_name'] ?? '') ?>
                                            · Size <?= lm_cart_h($row['size_name'] ?? '') ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= lm_cart_h($row['color_name'] ?? '') ?>
                                /
                                <?= lm_cart_h($row['size_name'] ?? '') ?>
                            </td>
                            <td><?= lm_cart_money($price) ?></td>
                            <td>
                                <div class="lm-cart-qty">
                                    <button type="button" data-qty-step="-1"
                                            aria-label="Giảm số lượng">−</button>
                                    <input type="number"
                                           name="quantities[<?= $id ?>]"
                                           value="<?= $quantity ?>"
                                           min="1" max="99" step="1"
                                           inputmode="numeric"
                                           aria-label="Số lượng sản phẩm">
                                    <button type="button" data-qty-step="1"
                                            aria-label="Tăng số lượng">+</button>
                                </div>
                            </td>
                            <td><?= lm_cart_money($price * $quantity) ?></td>
                            <td>
                                <button class="lm-cart-remove"
                                        type="submit"
                                        form="remove-<?= $id ?>">
                                    Xóa
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="lm-cart-actions">
                <a class="lm-cart-link" href="products.php">← Tiếp tục mua sắm</a>
                <button type="submit" class="lm-btn lm-btn-primary">
                    Cập nhật giỏ hàng
                </button>
            </div>
        </form>

        <?php foreach ($cart as $key => $row): ?>
            <form id="remove-<?= (int)$key ?>" method="post" action="cart.php"
                  style="display:none">
                <input type="hidden" name="csrf_token"
                       value="<?= lm_cart_h($_SESSION['lm_cart_csrf']) ?>">
                <input type="hidden" name="cart_action" value="remove">
                <input type="hidden" name="variant_id" value="<?= (int)$key ?>">
            </form>
        <?php endforeach; ?>

        <section class="lm-cart-summary">
            <h2>Tóm tắt đơn hàng</h2>

            <p class="lm-cart-summary-row">
                <span>Số lượng sản phẩm</span>
                <strong><?= $count ?></strong>
            </p>

            <p class="lm-cart-summary-row lm-cart-total">
                <span>Tạm tính</span>
                <strong><?= lm_cart_money($total) ?></strong>
            </p>

            <p class="lm-cart-note">
                Phí vận chuyển và thanh toán sẽ được tính ở bước tiếp theo.
                Giá và tồn kho được kiểm tra lại khi cập nhật giỏ hàng.
            </p>
<a href="checkout.php" class="lm-btn lm-btn-primary"
   style="width:100%;display:flex;justify-content:center;
          text-decoration:none;box-sizing:border-box">
    Tiến hành thanh toán
</a>
        </section>
    <?php endif; ?>
</main>

<script>
document.querySelectorAll('[data-qty-step]').forEach(button => {
    button.addEventListener('click', () => {
        const wrap = button.closest('.lm-cart-qty');
        const input = wrap?.querySelector('input[type="number"]');
        if (!input) return;

        const step = Number(button.dataset.qtyStep || 0);
        const current = Number.parseInt(input.value, 10) || 1;
        const min = Number(input.min) || 1;
        const max = Number(input.max) || 99;

        input.value = String(Math.min(max, Math.max(min, current + step)));
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
