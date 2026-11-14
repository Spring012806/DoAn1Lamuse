<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function lm_cart_h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lm_cart_money($value): string
{
    return number_format((float)$value, 0, ',', '.') . '₫';
}

function lm_cart_redirect(string $to = 'cart.php'): never
{
    header('Location: ' . $to);
    exit;
}

function lm_cart_flash(string $message, string $type = 'success'): void
{
    $_SESSION['lm_cart_flash'] = ['message' => $message, 'type' => $type];
}

function lm_cart_price(array $v): float
{
    if (isset($v['gia_ban']) && (float)$v['gia_ban'] > 0) {
        return (float)$v['gia_ban'];
    }
    if (
        isset($v['gia_khuyen_mai']) && (float)$v['gia_khuyen_mai'] > 0
        && (float)$v['gia_khuyen_mai'] < (float)($v['gia_niem_yet'] ?? 0)
    ) {
        return (float)$v['gia_khuyen_mai'];
    }
    return (float)($v['gia_niem_yet'] ?? 0);
}

function lm_cart_fetch_variant(PDO $pdo, int $variantId, int $productId = 0, bool $lock = false): ?array
{
    $sql = "
        SELECT bt.id, bt.sku, bt.san_pham_id, bt.so_luong_ton, bt.gia_ban,
               bt.mau_sac_id, bt.kich_thuoc_id,
               sp.ten_san_pham, sp.ma_san_pham, sp.gia_niem_yet, sp.gia_khuyen_mai,
               sp.anh_dai_dien, ms.ten_mau, kt.ten_size
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
          AND kt.trang_thai = 1";
    if ($productId > 0) {
        $sql .= ' AND bt.san_pham_id = :product_id';
    }
    $sql .= ' LIMIT 1';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $params = ['id' => $variantId];
    if ($productId > 0) {
        $params['product_id'] = $productId;
    }
    $stmt->execute($params);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}

function lm_cart_row_from_variant(array $v, int $quantity, ?string $image = null): array
{
    return [
        'variant_id' => (int)$v['id'],
        'product_id' => (int)$v['san_pham_id'],
        'sku' => (string)$v['sku'],
        'name' => (string)$v['ten_san_pham'],
        'product_code' => (string)$v['ma_san_pham'],
        'color_id' => (int)$v['mau_sac_id'],
        'color_name' => (string)$v['ten_mau'],
        'size_id' => (int)$v['kich_thuoc_id'],
        'size_name' => (string)$v['ten_size'],
        'price' => lm_cart_price($v),
        'quantity' => $quantity,
        'stock' => (int)$v['so_luong_ton'],
        'image' => $image !== null && $image !== '' ? $image : (string)($v['anh_dai_dien'] ?? ''),
    ];
}

if (empty($_SESSION['lm_cart_csrf'])) {
    $_SESSION['lm_cart_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['lm_cart']) || !is_array($_SESSION['lm_cart'])) {
    $_SESSION['lm_cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals((string)$_SESSION['lm_cart_csrf'], $token)) {
        http_response_code(403);
        exit('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
    }

    $action = is_string($_POST['cart_action'] ?? null) ? $_POST['cart_action'] : '';
    $intent = is_string($_POST['intent'] ?? null) ? $_POST['intent'] : 'update';

    if ($action === 'remove') {
        unset($_SESSION['lm_checkout_mode'], $_SESSION['lm_buy_now']);
        $removeId = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
        if ($removeId === false || $removeId === null || $removeId < 1) {
            lm_cart_flash('Sản phẩm không hợp lệ.', 'error');
        } else {
            unset($_SESSION['lm_cart'][$removeId]);
            $selected = $_SESSION['lm_cart_selected'] ?? [];
            if (is_array($selected)) {
                $_SESSION['lm_cart_selected'] = array_values(array_filter(
                    $selected,
                    static fn($id): bool => (int)$id !== (int)$removeId
                ));
            }
            lm_cart_flash('Đã xóa sản phẩm khỏi giỏ hàng.');
        }
        lm_cart_redirect();
    }

    if ($action !== 'update') {
        lm_cart_flash('Thao tác không hợp lệ.', 'error');
        lm_cart_redirect();
    }

    // Thao tác trực tiếp trong giỏ sẽ thay thế trạng thái mua-ngay tạm trước đó.
    unset($_SESSION['lm_checkout_mode'], $_SESSION['lm_buy_now']);

    $oldCart = $_SESSION['lm_cart'];
    $postedQuantities = $_POST['quantities'] ?? [];
    $postedChoices = $_POST['variant_choices'] ?? [];
    $postedSelected = $_POST['selected'] ?? [];

    if (!is_array($postedQuantities) || !is_array($postedChoices) || !is_array($postedSelected)) {
        lm_cart_flash('Dữ liệu giỏ hàng không hợp lệ.', 'error');
        lm_cart_redirect();
    }

    $selectedOldIds = [];
    foreach ($postedSelected as $rawId) {
        $validId = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($validId !== false && $validId !== null && $validId > 0) {
            $selectedOldIds[(int)$validId] = true;
        }
    }

    $newCart = [];
    $newSelected = [];
    $messages = [];

    try {
        $pdo->beginTransaction();

        foreach ($oldCart as $key => $oldRow) {
            $oldId = filter_var($key, FILTER_VALIDATE_INT);
            if ($oldId === false || $oldId === null || $oldId < 1 || !is_array($oldRow)) {
                $messages[] = 'Đã bỏ qua một dòng giỏ hàng không hợp lệ.';
                continue;
            }

            $productId = (int)($oldRow['product_id'] ?? 0);
            if ($productId < 1) {
                $messages[] = 'Một sản phẩm thiếu dữ liệu và không thể cập nhật.';
                continue;
            }

            $choice = $postedChoices[$oldId] ?? $postedChoices[(string)$oldId] ?? [];
            if (!is_array($choice)) {
                $choice = [];
            }
            $colorId = filter_var($choice['color_id'] ?? null, FILTER_VALIDATE_INT);
            $sizeId = filter_var($choice['size_id'] ?? null, FILTER_VALIDATE_INT);

            $v = null;
            if ($colorId !== false && $colorId !== null && $colorId > 0
                && $sizeId !== false && $sizeId !== null && $sizeId > 0) {
                $findVariant = $pdo->prepare("
                    SELECT bt.id, bt.sku, bt.san_pham_id, bt.so_luong_ton, bt.gia_ban,
                           bt.mau_sac_id, bt.kich_thuoc_id,
                           sp.ten_san_pham, sp.ma_san_pham, sp.gia_niem_yet, sp.gia_khuyen_mai,
                           sp.anh_dai_dien, ms.ten_mau, kt.ten_size
                    FROM bien_the_sanpham bt
                    JOIN sanpham sp ON sp.id = bt.san_pham_id
                    JOIN danhmuc dm ON dm.id = sp.danh_muc_id
                    JOIN mausac ms ON ms.id = bt.mau_sac_id
                    JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
                    WHERE bt.san_pham_id = :product_id
                      AND bt.mau_sac_id = :color_id
                      AND bt.kich_thuoc_id = :size_id
                      AND bt.trang_thai = 1
                      AND sp.trang_thai = 'dang_ban'
                      AND dm.trang_thai = 1
                      AND ms.trang_thai = 1
                      AND kt.trang_thai = 1
                    LIMIT 1 FOR UPDATE
                ");
                $findVariant->execute([
                    'product_id' => $productId,
                    'color_id' => $colorId,
                    'size_id' => $sizeId,
                ]);
                $v = $findVariant->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!$v) {
                $v = lm_cart_fetch_variant($pdo, (int)$oldId, $productId, true);
                $messages[] = 'Không tìm thấy tổ hợp màu và size mới của '
                    . (string)($oldRow['name'] ?? 'sản phẩm')
                    . '; lựa chọn cũ được giữ lại nếu còn khả dụng.';
            }

            if (!$v) {
                $messages[] = (string)($oldRow['name'] ?? 'Một sản phẩm')
                    . ' không còn khả dụng và đã được loại khỏi giỏ.';
                continue;
            }

            $rawQty = $postedQuantities[$oldId] ?? $postedQuantities[(string)$oldId] ?? ($oldRow['quantity'] ?? 1);
            $quantity = filter_var($rawQty, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity === null || $quantity < 1) {
                $quantity = max(1, (int)($oldRow['quantity'] ?? 1));
                $messages[] = 'Một số lượng không hợp lệ đã được giữ ở giá trị cũ.';
            }
            $quantity = min(99, (int)$quantity);

            $stock = (int)$v['so_luong_ton'];
            if ($stock > 0 && $quantity > $stock) {
                $quantity = $stock;
                $messages[] = (string)$v['ten_san_pham']
                    . ' được điều chỉnh còn ' . $stock . ' sản phẩm theo tồn kho.';
            } elseif ($stock < 1) {
                $messages[] = (string)$v['ten_san_pham']
                    . ' đã hết hàng. Nàng có thể đổi màu/size hoặc xóa sản phẩm.';
            }

            $image = !empty($oldRow['image']) ? (string)$oldRow['image'] : null;
            $freshRow = lm_cart_row_from_variant($v, $quantity, $image);
            $newId = (int)$v['id'];

            if (isset($newCart[$newId])) {
                $combined = (int)$newCart[$newId]['quantity'] + $quantity;
                if ($stock > 0 && $combined > $stock) {
                    $combined = $stock;
                    $messages[] = 'Hai dòng cùng biến thể đã được gộp; số lượng được giới hạn theo tồn kho.';
                } else {
                    $combined = min(99, $combined);
                    $messages[] = 'Các dòng có cùng màu và size đã được gộp.';
                }
                $newCart[$newId]['quantity'] = max(1, $combined);
                if (empty($newCart[$newId]['image']) && !empty($freshRow['image'])) {
                    $newCart[$newId]['image'] = $freshRow['image'];
                }
            } else {
                $newCart[$newId] = $freshRow;
            }

            if (isset($selectedOldIds[(int)$oldId])) {
                $newSelected[$newId] = $newId;
            }
        }

        $pdo->commit();
        $_SESSION['lm_cart'] = $newCart;
        $_SESSION['lm_cart_selected'] = array_values($newSelected);

        if ($intent === 'checkout_selected') {
            if (!$newSelected) {
                lm_cart_flash('Nàng hãy chọn ít nhất một sản phẩm để thanh toán.', 'error');
                lm_cart_redirect();
            }

            if ($messages) {
                lm_cart_flash(implode(' ', array_values(array_unique($messages)))
                    . ' Vui lòng kiểm tra giỏ hàng trước khi thanh toán.', 'error');
                lm_cart_redirect();
            }

            unset($_SESSION['lm_checkout_mode'], $_SESSION['lm_buy_now']);
            lm_cart_redirect('thanhtoan.php');
        }

        if ($messages) {
            lm_cart_flash(implode(' ', array_values(array_unique($messages))), 'error');
        } else {
            lm_cart_flash('Giỏ hàng đã được cập nhật.');
        }
        lm_cart_redirect();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        lm_cart_flash('Chưa thể cập nhật giỏ hàng. Vui lòng thử lại.', 'error');
        lm_cart_redirect();
    }
}

$cart = $_SESSION['lm_cart'] ?? [];
if (!is_array($cart)) {
    $cart = [];
}

// Lần đầu vào giỏ, chọn sẵn toàn bộ sản phẩm. Sau đó giữ nguyên lựa chọn của khách.
if (!array_key_exists('lm_cart_selected', $_SESSION)) {
    $_SESSION['lm_cart_selected'] = array_map('intval', array_keys($cart));
}
$selectedIds = array_values(array_unique(array_map('intval', is_array($_SESSION['lm_cart_selected']) ? $_SESSION['lm_cart_selected'] : [])));
$selectedSet = array_fill_keys($selectedIds, true);

$flash = $_SESSION['lm_cart_flash'] ?? null;
unset($_SESSION['lm_cart_flash']);

$productIds = [];
foreach ($cart as $row) {
    if (is_array($row) && (int)($row['product_id'] ?? 0) > 0) {
        $productIds[] = (int)$row['product_id'];
    }
}
$productIds = array_values(array_unique($productIds));

$variantsByProduct = [];
$variantById = [];
if ($productIds) {
    $marks = implode(',', array_fill(0, count($productIds), '?'));
    $catalogStmt = $pdo->prepare("
        SELECT bt.id AS variant_id, bt.sku, bt.san_pham_id, bt.mau_sac_id, bt.kich_thuoc_id,
               bt.so_luong_ton, bt.gia_ban,
               sp.ten_san_pham, sp.ma_san_pham, sp.gia_niem_yet, sp.gia_khuyen_mai, sp.anh_dai_dien,
               ms.ten_mau, ms.ma_hex, kt.ten_size, kt.thu_tu
        FROM bien_the_sanpham bt
        JOIN sanpham sp ON sp.id = bt.san_pham_id
        JOIN danhmuc dm ON dm.id = sp.danh_muc_id
        JOIN mausac ms ON ms.id = bt.mau_sac_id
        JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
        WHERE bt.san_pham_id IN ($marks)
          AND bt.trang_thai = 1 AND sp.trang_thai = 'dang_ban'
          AND dm.trang_thai = 1 AND ms.trang_thai = 1 AND kt.trang_thai = 1
        ORDER BY bt.san_pham_id, ms.id, kt.thu_tu, kt.id
    ");
    $catalogStmt->execute($productIds);
    foreach ($catalogStmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $price = lm_cart_price($v);
        $entry = [
            'id' => (int)$v['variant_id'],
            'sku' => (string)$v['sku'],
            'productId' => (int)$v['san_pham_id'],
            'colorId' => (int)$v['mau_sac_id'],
            'colorName' => (string)$v['ten_mau'],
            'hex' => (string)($v['ma_hex'] ?: '#E5E0D6'),
            'sizeId' => (int)$v['kich_thuoc_id'],
            'sizeName' => (string)$v['ten_size'],
            'sizeOrder' => (int)$v['thu_tu'],
            'stock' => (int)$v['so_luong_ton'],
            'price' => $price,
            'image' => (string)($v['anh_dai_dien'] ?? ''),
            'name' => (string)$v['ten_san_pham'],
        ];
        $variantsByProduct[(int)$v['san_pham_id']][] = $entry;
        $variantById[(int)$v['variant_id']] = $entry;
    }
}

// Làm mới giá, tên biến thể và tồn kho đang hiển thị bằng dữ liệu mới nhất.
foreach ($cart as $key => &$row) {
    $id = (int)$key;
    $productId = (int)($row['product_id'] ?? 0);
    if (isset($variantById[$id])) {
        $v = $variantById[$id];
        $row['variant_id'] = $id;
        $row['sku'] = $v['sku'];
        $row['name'] = $v['name'];
        $row['color_id'] = $v['colorId'];
        $row['color_name'] = $v['colorName'];
        $row['size_id'] = $v['sizeId'];
        $row['size_name'] = $v['sizeName'];
        $row['price'] = $v['price'];
        $row['stock'] = $v['stock'];
        if (empty($row['image'])) {
            $row['image'] = $v['image'];
        }
    }
}
unset($row);
$_SESSION['lm_cart'] = $cart;

// Bỏ khỏi danh sách chọn các ID không còn tồn tại trong giỏ.
$selectedIds = array_values(array_filter($selectedIds, static fn(int $id): bool => isset($cart[$id])));
$_SESSION['lm_cart_selected'] = $selectedIds;
$selectedSet = array_fill_keys($selectedIds, true);

$allCount = 0;
$selectedCount = 0;
$selectedTotal = 0.0;
foreach ($cart as $key => $row) {
    $quantity = max(1, (int)($row['quantity'] ?? 1));
    $allCount += $quantity;
    if (isset($selectedSet[(int)$key])) {
        $selectedCount += $quantity;
        $selectedTotal += max(0, (float)($row['price'] ?? 0)) * $quantity;
    }
}

$jsCatalog = [];
foreach ($variantsByProduct as $pid => $list) {
    $jsCatalog[(string)$pid] = $list;
}

require_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="chitietsanpham.css">
<link rel="stylesheet" href="cart.css">

<main class="lm-page lm-cart-page">
    <section class="lm-cart-hero" aria-label="Giỏ hàng của nàng">
        <div class="lm-cart-hero-inner">
            <p class="lm-cart-hero-kicker">GIỎ HÀNG</p>
            <h1 class="lm-cart-title">Giỏ hàng của nàng</h1>
            <p class="lm-cart-hero-subtitle">Những món đồ xinh đẹp đang chờ được trở thành một phần<br class="lm-cart-desktop-break"> trong câu chuyện của nàng...</p>
            <span class="lm-cart-hero-flourish" aria-hidden="true">— ◇ —</span>
        </div>
    </section>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="lm-cart-alert <?= ($flash['type'] ?? '') === 'error' ? 'is-error' : '' ?>" role="status">
            <?= lm_cart_h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$cart): ?>
        <div class="lm-cart-empty">
            <h2>Một khoảng trống dịu dàng</h2>
            <p>Giỏ hàng của nàng vẫn đang chờ những thiết kế yêu thích.<br>Hãy cùng La Muse tìm món đồ dành riêng cho câu chuyện của nàng.</p>
            <a class="lm-btn lm-btn-primary" href="products.php">Khám phá bộ sưu tập</a>
        </div>
    <?php else: ?>
        <div class="lm-cart-layout">
            <form method="post" action="cart.php" id="lmCartForm" class="lm-cart-form">
                <input type="hidden" name="csrf_token" value="<?= lm_cart_h($_SESSION['lm_cart_csrf']) ?>">
                <input type="hidden" name="cart_action" value="update">

                <div class="lm-cart-select-all-row">
                    <label class="lm-cart-check-label">
                        <input type="checkbox" id="lmSelectAll" <?= count($selectedIds) === count($cart) ? 'checked' : '' ?>>
                        <span>Chọn tất cả</span>
                    </label>
                    <span class="lm-cart-small-note">Giá và tồn kho được kiểm tra lại trước khi đặt hàng.</span>
                </div>

                <div class="lm-cart-table-wrap">
                    <table class="lm-cart-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th><th>Màu sắc &amp; size</th><th>Đơn giá</th><th>Số lượng</th><th>Tạm tính</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cart as $key => $row):
                            $id = (int)$key;
                            $pid = (int)($row['product_id'] ?? 0);
                            $quantity = max(1, (int)($row['quantity'] ?? 1));
                            $currentVariant = $variantById[$id] ?? null;
                            $price = (float)($currentVariant['price'] ?? $row['price'] ?? 0);
                            $stock = isset($currentVariant['stock']) ? (int)$currentVariant['stock'] : null;
                            $options = $variantsByProduct[$pid] ?? [];
                            $colorOptions = [];
                            foreach ($options as $option) {
                                $colorOptions[$option['colorId']] = $option;
                            }
                            $statusText = $stock === null ? 'Biến thể không còn khả dụng.' : ($stock < 1 ? 'Hết hàng — hãy đổi màu/size hoặc xóa sản phẩm.' : ($stock <= 5 ? 'Chỉ còn ' . $stock . ' sản phẩm' : 'Còn hàng · ' . $stock . ' sản phẩm'));
                        ?>
                            <tr class="lm-cart-row" data-product-id="<?= $pid ?>" data-original-variant-id="<?= $id ?>">
                                <td class="lm-cart-product-cell">
                                    <div class="lm-cart-product-card">
                                        <label class="lm-cart-item-check" aria-label="Chọn <?= lm_cart_h($row['name'] ?? 'sản phẩm') ?> để thanh toán">
                                            <input class="lm-cart-select" type="checkbox" name="selected[]" value="<?= $id ?>" <?= isset($selectedSet[$id]) ? 'checked' : '' ?>>
                                        </label>
                                        <a class="lm-cart-thumb" href="chitietsanpham.php?id=<?= $pid ?>">
                                            <?php if (!empty($row['image'])): ?>
                                                <img src="<?= lm_cart_h($row['image']) ?>" alt="<?= lm_cart_h($row['name'] ?? 'Sản phẩm La Muse') ?>" loading="lazy">
                                            <?php else: ?>
                                                <span class="lm-cart-thumb-placeholder">La Muse</span>
                                            <?php endif; ?>
                                        </a>
                                        <div class="lm-cart-product-details">
                                            <a class="lm-cart-product-name" href="chitietsanpham.php?id=<?= $pid ?>"><?= lm_cart_h($row['name'] ?? 'Sản phẩm') ?></a>
                                            <small class="lm-cart-sku"><?= lm_cart_h($currentVariant['sku'] ?? $row['sku'] ?? '') ?></small>
                                            <span class="lm-cart-stock <?= $stock !== null && $stock < 1 ? 'is-out' : '' ?>" data-stock-label><?= lm_cart_h($statusText) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="lm-cart-variant-cell">
                                    <label class="lm-cart-field-label">Màu sắc</label>
                                    <select class="lm-cart-variant-select lm-cart-color-select" name="variant_choices[<?= $id ?>][color_id]" aria-label="Chọn màu sắc" <?= !$options ? 'disabled' : '' ?>>
                                        <?php foreach ($colorOptions as $color): ?>
                                            <option value="<?= (int)$color['colorId'] ?>" <?= (int)($currentVariant['colorId'] ?? $row['color_id'] ?? 0) === (int)$color['colorId'] ? 'selected' : '' ?>><?= lm_cart_h($color['colorName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="lm-cart-field-label">Kích thước</label>
                                    <select class="lm-cart-variant-select lm-cart-size-select" name="variant_choices[<?= $id ?>][size_id]" aria-label="Chọn kích thước" <?= !$options ? 'disabled' : '' ?>>
                                        <?php foreach ($options as $option):
                                            if ((int)$option['colorId'] !== (int)($currentVariant['colorId'] ?? $row['color_id'] ?? 0)) continue;
                                        ?>
                                            <option value="<?= (int)$option['sizeId'] ?>" <?= (int)($currentVariant['sizeId'] ?? $row['size_id'] ?? 0) === (int)$option['sizeId'] ? 'selected' : '' ?>><?= lm_cart_h($option['sizeName']) ?><?= (int)$option['stock'] < 1 ? ' — Hết hàng' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="lm-cart-money-cell"><span class="lm-cart-unit-price" data-unit-price><?= lm_cart_money($price) ?></span></td>
                                <td>
                                    <div class="lm-cart-qty">
                                        <button type="button" data-qty-step="-1" aria-label="Giảm số lượng">−</button>
                                        <input type="number" name="quantities[<?= $id ?>]" value="<?= $quantity ?>" min="1" max="<?= $stock !== null && $stock > 0 ? $stock : 99 ?>" step="1" inputmode="numeric" aria-label="Số lượng sản phẩm">
                                        <button type="button" data-qty-step="1" aria-label="Tăng số lượng">+</button>
                                    </div>
                                </td>
                                <td class="lm-cart-money-cell"><strong class="lm-cart-line-total" data-line-total><?= lm_cart_money($price * $quantity) ?></strong></td>
                                <td>
                                    <button class="lm-cart-remove" type="submit" form="lm-cart-remove-<?= $id ?>">Xóa</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="lm-cart-actions">
                    <a class="lm-cart-link" href="products.php">← Tiếp tục mua sắm</a>
                    <button type="submit" class="lm-btn lm-btn-outline" name="intent" value="update">Cập nhật giỏ hàng</button>
                </div>
            </form>

            <aside class="lm-cart-summary">
                <h2>Tóm tắt đơn hàng</h2>
                <p class="lm-cart-summary-row"><span>Tạm tính sản phẩm (<?= $selectedCount ?>)</span><strong id="lmSelectedSubtotal"><?= lm_cart_money($selectedTotal) ?></strong></p>
                <p class="lm-cart-summary-row"><span>Phí vận chuyển</span><strong class="lm-cart-free-shipping">Miễn phí*</strong></p>
                <p class="lm-cart-summary-row lm-cart-total"><span>Tổng tạm tính</span><strong id="lmSelectedTotal"><?= lm_cart_money($selectedTotal) ?></strong></p>
                <p class="lm-cart-note"><span class="lm-cart-leaf" aria-hidden="true">❧</span> Đơn hàng của nàng được miễn phí vận chuyển khi thanh toán trực tuyến.</p>
                <button type="submit" form="lmCartForm" name="intent" value="checkout_selected" id="lmCheckoutSelected" class="lm-btn lm-btn-primary" <?= $selectedCount < 1 ? 'disabled' : '' ?>>THANH TOÁN NGAY <span aria-hidden="true">→</span></button>
                <a href="products.php" class="lm-btn lm-btn-outline lm-cart-continue-button">Tiếp tục mua sắm</a>
                <div class="lm-cart-service-row">
                    <div><span aria-hidden="true">♧</span><small>Giao hàng nhanh<br>trên toàn quốc</small></div>
                    <div><span aria-hidden="true">♢</span><small>Thanh toán an toàn<br>&amp; bảo mật</small></div>
                    <div><span aria-hidden="true">♡</span><small>Hỗ trợ 24/7<br>luôn bên nàng</small></div>
                </div>
            </aside>
        </div>

        <section class="lm-cart-editorial-note" aria-label="Lời nhắn từ La Muse">
            <p>“Thời trang không chỉ là trang phục,<br> mà là cách nàng kể câu chuyện của chính mình.”</p>
            <span>— La Muse</span>
        </section>

        <?php foreach ($cart as $key => $row): $id = (int)$key; ?>
            <form id="lm-cart-remove-<?= $id ?>" method="post" action="cart.php" class="lm-cart-remove-form">
                <input type="hidden" name="csrf_token" value="<?= lm_cart_h($_SESSION['lm_cart_csrf']) ?>">
                <input type="hidden" name="cart_action" value="remove">
                <input type="hidden" name="variant_id" value="<?= $id ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<script>
window.LM_CART_VARIANTS = <?= json_encode($jsCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
(() => {
    const money = value => new Intl.NumberFormat('vi-VN').format(Math.round(Number(value || 0))) + '₫';
    const form = document.getElementById('lmCartForm');
    if (!form) return;
    const rows = [...form.querySelectorAll('.lm-cart-row')];
    const selectAll = document.getElementById('lmSelectAll');
    const selectedCount = document.getElementById('lmSelectedCount');
    const selectedSubtotal = document.getElementById('lmSelectedSubtotal');
    const selectedTotal = document.getElementById('lmSelectedTotal');
    const checkoutButton = document.getElementById('lmCheckoutSelected');
    const catalog = window.LM_CART_VARIANTS || {};

    function optionsFor(row) {
        const pid = row.dataset.productId;
        return Array.isArray(catalog[pid]) ? catalog[pid] : [];
    }

    function findVariant(row) {
        const color = Number(row.querySelector('.lm-cart-color-select')?.value || 0);
        const size = Number(row.querySelector('.lm-cart-size-select')?.value || 0);
        return optionsFor(row).find(v => Number(v.colorId) === color && Number(v.sizeId) === size) || null;
    }

    function renderSizeOptions(row, preferredSize = null) {
        const colorSelect = row.querySelector('.lm-cart-color-select');
        const sizeSelect = row.querySelector('.lm-cart-size-select');
        if (!colorSelect || !sizeSelect) return;
        const colorId = Number(colorSelect.value || 0);
        const list = optionsFor(row).filter(v => Number(v.colorId) === colorId).sort((a, b) => Number(a.sizeOrder) - Number(b.sizeOrder));
        const current = preferredSize !== null ? Number(preferredSize) : Number(sizeSelect.value || 0);
        sizeSelect.replaceChildren();
        list.forEach(v => {
            const option = document.createElement('option');
            option.value = String(v.sizeId);
            option.textContent = `${v.sizeName}${Number(v.stock) < 1 ? ' — Hết hàng' : Number(v.stock) <= 5 ? ` — Còn ${v.stock}` : ''}`;
            sizeSelect.append(option);
        });
        const selected = list.find(v => Number(v.sizeId) === current);
        const fallback = list.find(v => Number(v.stock) > 0) || list[0];
        if (selected) sizeSelect.value = String(selected.sizeId);
        else if (fallback) sizeSelect.value = String(fallback.sizeId);
        sizeSelect.disabled = list.length === 0;
        if (list.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Không có size khả dụng';
            sizeSelect.append(option);
        }
    }

    function updateRow(row) {
        const variant = findVariant(row);
        const qty = row.querySelector('input[type="number"]');
        const stockLabel = row.querySelector('[data-stock-label]');
        const unitPrice = row.querySelector('[data-unit-price]');
        const lineTotal = row.querySelector('[data-line-total]');
        const oldQty = Math.max(1, Number.parseInt(qty?.value || '1', 10) || 1);
        if (!variant) {
            if (stockLabel) {
                stockLabel.textContent = 'Tổ hợp màu/size không khả dụng.';
                stockLabel.classList.add('is-out');
            }
            if (qty) qty.max = '99';
            if (unitPrice) unitPrice.textContent = '—';
            if (lineTotal) lineTotal.textContent = '—';
            row.dataset.targetVariantId = '';
            return;
        }
        const stock = Number(variant.stock || 0);
        const price = Number(variant.price || 0);
        if (stockLabel) {
            stockLabel.classList.toggle('is-out', stock < 1);
            stockLabel.textContent = stock < 1 ? 'Hết hàng — hãy đổi màu/size hoặc xóa sản phẩm.' : stock <= 5 ? `Chỉ còn ${stock} sản phẩm` : `Còn hàng · ${stock} sản phẩm`;
        }
        if (qty) {
            qty.max = String(stock > 0 ? stock : 99);
            if (stock > 0 && oldQty > stock) qty.value = String(stock);
        }
        if (unitPrice) unitPrice.textContent = money(price);
        const actualQty = Math.max(1, Number.parseInt(qty?.value || '1', 10) || 1);
        if (lineTotal) lineTotal.textContent = money(price * actualQty);
        row.dataset.targetVariantId = String(variant.id);
    }

    function refreshSummary() {
        let count = 0;
        let total = 0;
        let checked = 0;
        rows.forEach(row => {
            const checkbox = row.querySelector('.lm-cart-select');
            if (checkbox?.checked) {
                checked++;
                const variant = findVariant(row);
                const qty = Math.max(1, Number.parseInt(row.querySelector('input[type="number"]')?.value || '1', 10) || 1);
                count += qty;
                total += Number(variant?.price || 0) * qty;
            }
        });
        if (selectedCount) selectedCount.textContent = String(count);
        if (selectedSubtotal) selectedSubtotal.textContent = money(total);
        if (selectedTotal) selectedTotal.textContent = money(total);
        if (checkoutButton) checkoutButton.disabled = checked === 0;
        if (selectAll) {
            selectAll.checked = rows.length > 0 && rows.every(row => row.querySelector('.lm-cart-select')?.checked);
            selectAll.indeterminate = rows.some(row => row.querySelector('.lm-cart-select')?.checked) && !selectAll.checked;
        }
    }

    rows.forEach(row => {
        const colorSelect = row.querySelector('.lm-cart-color-select');
        const sizeSelect = row.querySelector('.lm-cart-size-select');
        const qty = row.querySelector('input[type="number"]');
        colorSelect?.addEventListener('change', () => {
            const oldSize = sizeSelect?.value;
            renderSizeOptions(row, oldSize);
            updateRow(row);
            refreshSummary();
        });
        sizeSelect?.addEventListener('change', () => { updateRow(row); refreshSummary(); });
        qty?.addEventListener('input', () => {
            let value = Number.parseInt(qty.value, 10);
            if (!Number.isFinite(value) || value < 1) value = 1;
            const max = Number(qty.max || 99);
            qty.value = String(Math.min(max, Math.min(99, value)));
            updateRow(row);
            refreshSummary();
        });
        row.querySelectorAll('[data-qty-step]').forEach(button => {
            button.addEventListener('click', () => {
                if (!qty) return;
                const step = Number(button.dataset.qtyStep || 0);
                const current = Number.parseInt(qty.value, 10) || 1;
                const max = Number(qty.max || 99);
                qty.value = String(Math.min(max, Math.max(1, current + step)));
                updateRow(row);
                refreshSummary();
            });
        });
        row.querySelector('.lm-cart-select')?.addEventListener('change', refreshSummary);
        renderSizeOptions(row);
        updateRow(row);
    });

    selectAll?.addEventListener('change', () => {
        rows.forEach(row => {
            const box = row.querySelector('.lm-cart-select');
            if (box) box.checked = selectAll.checked;
        });
        refreshSummary();
    });
    refreshSummary();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
