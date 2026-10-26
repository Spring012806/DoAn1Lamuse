<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 1. KHỞI TẠO VÀ KIỂM TRA ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

// Chỉ khách đã đăng nhập mới được đặt hàng.
if (!lm_is_logged_in()) {
    $_SESSION['lm_after_login'] = 'thanhtoan.php';
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(403);
    exit('Phiên đăng nhập không hợp lệ.');
}

/*
|--------------------------------------------------------------------------
| 2. CẤU HÌNH NGÂN HÀNG NHẬN TIỀN
|--------------------------------------------------------------------------
| VietQR sử dụng mã ngân hàng VCB của Vietcombank.
| QR sẽ tự điền số tiền và mã đơn hàng.
*/

$bankCode = 'VCB';
$bankAccount = '1050043200';
$bankAccountName = 'LA MUSE';
$shippingFeeDefault = 30000;

/*
|--------------------------------------------------------------------------
| 3. HÀM HỖ TRỢ
|--------------------------------------------------------------------------
*/

function lm_checkout_h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function lm_checkout_money($value): string
{
    return number_format((float) $value, 0, ',', '.') . '₫';
}

function lm_checkout_redirect_login(): never
{
    $_SESSION['lm_after_login'] = 'thanhtoan.php';
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. KHỞI TẠO CSRF VÀ BIẾN GIAO DIỆN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['lm_checkout_csrf'])) {
    $_SESSION['lm_checkout_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['lm_checkout_csrf'];

$cart = $_SESSION['lm_cart'] ?? [];

if (!is_array($cart)) {
    $cart = [];
}

$errorMessage = '';
$successMessage = '';
$order = null;
$orderItems = [];
$qrUrl = '';

$formValues = [
    'fullname' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'note' => '',
    'payment' => 'cod',
];

/*
|--------------------------------------------------------------------------
| 5. XỬ LÝ TẠO ĐƠN HÀNG
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken)
        || !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
    }

    $formValues['fullname'] = trim(
        (string) ($_POST['fullname'] ?? '')
    );
    $formValues['phone'] = trim(
        (string) ($_POST['phone'] ?? '')
    );
    $formValues['email'] = trim(
        (string) ($_POST['email'] ?? '')
    );
    $formValues['address'] = trim(
        (string) ($_POST['address'] ?? '')
    );
    $formValues['note'] = trim(
        (string) ($_POST['note'] ?? '')
    );
    $formValues['payment'] = (string) ($_POST['payment'] ?? 'cod');

    try {
        // Kiểm tra thông tin người nhận.
        if (
            $formValues['fullname'] === ''
            || mb_strlen($formValues['fullname']) > 100
        ) {
            throw new RuntimeException(
                'Vui lòng nhập họ tên người nhận hợp lệ.'
            );
        }

        if (
            !preg_match(
                '/^[0-9+\s().-]{7,20}$/',
                $formValues['phone']
            )
        ) {
            throw new RuntimeException(
                'Số điện thoại không hợp lệ.'
            );
        }

        if (
            $formValues['address'] === ''
            || mb_strlen($formValues['address']) > 255
        ) {
            throw new RuntimeException(
                'Vui lòng nhập địa chỉ nhận hàng hợp lệ.'
            );
        }

        if (
            $formValues['email'] !== ''
            && !filter_var(
                $formValues['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Địa chỉ email không hợp lệ.'
            );
        }

        if (!in_array($formValues['payment'], ['cod', 'bank'], true)) {
            throw new RuntimeException(
                'Phương thức thanh toán không hợp lệ.'
            );
        }

        if (
            $formValues['note'] !== ''
            && mb_strlen($formValues['note']) > 5000
        ) {
            throw new RuntimeException(
                'Ghi chú đơn hàng quá dài.'
            );
        }

        // Luôn đọc lại giỏ hàng từ session phía máy chủ.
        $cart = $_SESSION['lm_cart'] ?? [];

        if (!is_array($cart) || !$cart) {
            throw new RuntimeException(
                'Giỏ hàng trống. Vui lòng thêm sản phẩm trước khi đặt hàng.'
            );
        }

        $pdo->beginTransaction();

        /*
         * Đọc lại thông tin biến thể, giá và tồn kho từ database.
         * Không tin giá do trình duyệt hoặc session gửi lên.
         */
        $variantStmt = $pdo->prepare("
            SELECT
                bt.id AS variant_id,
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
            JOIN sanpham sp
                ON sp.id = bt.san_pham_id
            JOIN danhmuc dm
                ON dm.id = sp.danh_muc_id
            JOIN mausac ms
                ON ms.id = bt.mau_sac_id
            JOIN kichthuoc kt
                ON kt.id = bt.kich_thuoc_id
            WHERE bt.id = :id
              AND bt.trang_thai = 1
              AND sp.trang_thai = 'dang_ban'
              AND dm.trang_thai = 1
              AND ms.trang_thai = 1
              AND kt.trang_thai = 1
            LIMIT 1
            FOR UPDATE
        ");

        $validatedItems = [];
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($cart as $key => $cartRow) {
            $variantId = filter_var($key, FILTER_VALIDATE_INT);

            if (
                $variantId === false
                || $variantId < 1
                || !is_array($cartRow)
            ) {
                throw new RuntimeException(
                    'Có sản phẩm không hợp lệ trong giỏ hàng.'
                );
            }

            $quantity = filter_var(
                $cartRow['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $quantity === false
                || $quantity < 1
                || $quantity > 99
            ) {
                throw new RuntimeException(
                    'Số lượng sản phẩm không hợp lệ.'
                );
            }

            $variantStmt->execute(['id' => $variantId]);
            $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

            if (!$variant) {
                throw new RuntimeException(
                    'Một sản phẩm đã ngừng bán hoặc không còn hợp lệ. '
                    . 'Vui lòng kiểm tra lại giỏ hàng.'
                );
            }

            $stock = (int) $variant['so_luong_ton'];

            if ($stock < $quantity) {
                throw new RuntimeException(
                    'Sản phẩm '
                    . $variant['ten_san_pham']
                    . ' không đủ tồn kho. Hiện còn '
                    . $stock
                    . ' sản phẩm.'
                );
            }

            // Tính giá theo quy tắc hiện có trong cart.php.
            if (
                $variant['gia_ban'] !== null
                && (float) $variant['gia_ban'] > 0
            ) {
                $price = (int) round((float) $variant['gia_ban']);
            } elseif (
                $variant['gia_khuyen_mai'] !== null
                && (float) $variant['gia_khuyen_mai'] > 0
                && (float) $variant['gia_khuyen_mai']
                    < (float) $variant['gia_niem_yet']
            ) {
                $price = (int) round(
                    (float) $variant['gia_khuyen_mai']
                );
            } else {
                $price = (int) round(
                    (float) $variant['gia_niem_yet']
                );
            }

            if ($price < 0) {
                throw new RuntimeException(
                    'Giá sản phẩm không hợp lệ.'
                );
            }

            $lineTotal = $price * $quantity;

            $validatedItems[] = [
                'variant_id' => (int) $variant['variant_id'],
                'name' => (string) $variant['ten_san_pham'],
                'product_code' => $variant['ma_san_pham'],
                'color' => $variant['ten_mau'],
                'size' => $variant['ten_size'],
                'price' => $price,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
            $totalQuantity += $quantity;
        }

        if (!$validatedItems || $totalQuantity < 1) {
            throw new RuntimeException(
                'Giỏ hàng không có sản phẩm hợp lệ.'
            );
        }

        $shippingFee = $shippingFeeDefault;
        $discount = 0;
        $grandTotal = $subtotal + $shippingFee - $discount;

        /*
         * Tạo mã đơn hàng riêng.
         * Cột ma_don_hang đã có ràng buộc UNIQUE.
         */
        $orderCode = 'LM'
            . date('ymd')
            . strtoupper(bin2hex(random_bytes(3)));

        /*
         * Kiểm tra lại tài khoản vẫn tồn tại trước khi lưu đơn.
         * Không sử dụng user_id do form gửi lên.
         */
        $userStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $userStmt->execute(['user_id' => $userId]);

        if (!$userStmt->fetchColumn()) {
            throw new RuntimeException(
                'Tài khoản không còn hợp lệ. Vui lòng đăng nhập lại.'
            );
        }

        $paymentMethod = $formValues['payment'] === 'bank'
            ? 'bank_transfer'
            : 'cod';

        /*
         * Lưu đơn hàng.
         * Đơn chuyển khoản mặc định chưa thanh toán.
         * Đơn COD cũng chưa thanh toán trước khi giao hàng.
         */
        $insertOrder = $pdo->prepare("
            INSERT INTO don_hang (
                ma_don_hang,
                user_id,
                ten_nguoi_nhan,
                so_dien_thoai,
                email,
                dia_chi,
                ghi_chu,
                tong_tien,
                phi_van_chuyen,
                giam_gia,
                thanh_tien,
                phuong_thuc_thanh_toan,
                trang_thai_thanh_toan,
                trang_thai_don_hang
            ) VALUES (
                :ma_don_hang,
                :user_id,
                :ten_nguoi_nhan,
                :so_dien_thoai,
                :email,
                :dia_chi,
                :ghi_chu,
                :tong_tien,
                :phi_van_chuyen,
                :giam_gia,
                :thanh_tien,
                :phuong_thuc_thanh_toan,
                'unpaid',
                'pending'
            )
        ");

        $insertOrder->execute([
            'ma_don_hang' => $orderCode,
            'user_id' => $userId,
            'ten_nguoi_nhan' => $formValues['fullname'],
            'so_dien_thoai' => $formValues['phone'],
            'email' => $formValues['email'] !== ''
                ? $formValues['email']
                : null,
            'dia_chi' => $formValues['address'],
            'ghi_chu' => $formValues['note'] !== ''
                ? $formValues['note']
                : null,
            'tong_tien' => $subtotal,
            'phi_van_chuyen' => $shippingFee,
            'giam_gia' => $discount,
            'thanh_tien' => $grandTotal,
            'phuong_thuc_thanh_toan' => $paymentMethod,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        /*
         * Lưu chi tiết từng sản phẩm.
         */
        $insertItem = $pdo->prepare("
            INSERT INTO chi_tiet_don_hang (
                don_hang_id,
                bien_the_id,
                ten_san_pham,
                ma_san_pham,
                ten_mau,
                ten_size,
                don_gia,
                so_luong,
                thanh_tien
            ) VALUES (
                :don_hang_id,
                :bien_the_id,
                :ten_san_pham,
                :ma_san_pham,
                :ten_mau,
                :ten_size,
                :don_gia,
                :so_luong,
                :thanh_tien
            )
        ");

        foreach ($validatedItems as $item) {
            $insertItem->execute([
                'don_hang_id' => $orderId,
                'bien_the_id' => $item['variant_id'],
                'ten_san_pham' => $item['name'],
                'ma_san_pham' => $item['product_code'],
                'ten_mau' => $item['color'],
                'ten_size' => $item['size'],
                'don_gia' => $item['price'],
                'so_luong' => $item['quantity'],
                'thanh_tien' => $item['line_total'],
            ]);
        }

        // Chỉ commit khi cả đơn hàng và chi tiết đều được lưu.
        $pdo->commit();

        // Xóa giỏ hàng sau khi lưu đơn hàng thành công.
        $_SESSION['lm_cart'] = [];

        // Đổi token sau thao tác đặt hàng.
        $_SESSION['lm_checkout_csrf'] = bin2hex(random_bytes(32));

        // Chuyển hướng để tránh gửi lại form khi tải lại trang.
        header('Location: thanhtoan.php?order=' . $orderId);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Ghi lỗi kỹ thuật vào log máy chủ, không hiển thị SQL cho khách.
        error_log(
            '[La Muse checkout] ' . $e->getMessage()
        );

        if ($e instanceof RuntimeException) {
            $errorMessage = $e->getMessage();
        } else {
            $errorMessage =
                'Chưa thể tạo đơn hàng. Vui lòng thử lại '
                . 'hoặc liên hệ quản trị viên.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| 6. ĐỌC ĐƠN HÀNG VỪA TẠO
|--------------------------------------------------------------------------
| Chỉ chủ sở hữu đơn hàng mới được xem thông tin và QR.
*/

$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT);

if ($orderId !== false && $orderId !== null && $orderId > 0) {
    $orderStmt = $pdo->prepare("
        SELECT
            id,
            ma_don_hang,
            ten_nguoi_nhan,
            tong_tien,
            phi_van_chuyen,
            giam_gia,
            thanh_tien,
            phuong_thuc_thanh_toan,
            trang_thai_thanh_toan,
            trang_thai_don_hang
        FROM don_hang
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ");

    $orderStmt->execute([
        'id' => $orderId,
        'user_id' => $userId,
    ]);

    $order = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($order) {
        $itemStmt = $pdo->prepare("
            SELECT
                ten_san_pham,
                ma_san_pham,
                ten_mau,
                ten_size,
                don_gia,
                so_luong,
                thanh_tien
            FROM chi_tiet_don_hang
            WHERE don_hang_id = :id
            ORDER BY id ASC
        ");

        $itemStmt->execute(['id' => $orderId]);
        $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Chỉ tạo QR cho đơn chuyển khoản chưa được xác nhận thanh toán.
        if (
            $order['phuong_thuc_thanh_toan'] === 'bank_transfer'
            && $order['trang_thai_thanh_toan'] === 'unpaid'
        ) {
            $qrParams = http_build_query([
                'amount' => (int) $order['thanh_tien'],
                'addInfo' => $order['ma_don_hang'],
                'accountName' => $bankAccountName,
            ]);

            $qrUrl = 'https://img.vietqr.io/image/'
                . rawurlencode($bankCode)
                . '-'
                . rawurlencode($bankAccount)
                . '-compact2.png?'
                . $qrParams;
        }
    }
}

/*
|--------------------------------------------------------------------------
| 7. TÍNH DỮ LIỆU GIỎ HÀNG ĐỂ HIỂN THỊ
|--------------------------------------------------------------------------
*/

$cart = $_SESSION['lm_cart'] ?? [];

if (!is_array($cart)) {
    $cart = [];
}

$subtotal = 0;
$totalQuantity = 0;

foreach ($cart as $row) {
    if (!is_array($row)) {
        continue;
    }

    $price = max(0, (float) ($row['price'] ?? 0));
    $quantity = max(0, (int) ($row['quantity'] ?? 0));

    $subtotal += $price * $quantity;
    $totalQuantity += $quantity;
}

$shippingFee = $totalQuantity > 0 ? $shippingFeeDefault : 0;
$grandTotal = $subtotal + $shippingFee;

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="thanhtoan.css">

<main class="checkout-page">

    <section class="checkout-heading">
        <p class="checkout-eyebrow">
            LA MUSE — TISSEUSE DE RÊVES
        </p>

        <h1>Thanh toán</h1>

        <p class="checkout-description">
            Hoàn tất lựa chọn tinh tế dành riêng cho quý cô.
        </p>

        <a href="cart.php" class="back-to-cart">
            ← Quay lại giỏ hàng
        </a>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <div class="checkout-alert" role="alert">
            <?= lm_checkout_h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <?php if ($order): ?>

            <section class="checkout-form">
                <h2>Đơn hàng đã được tạo</h2>

                <p>
                    Mã đơn hàng:
                    <strong>
                        <?= lm_checkout_h($order['ma_don_hang']) ?>
                    </strong>
                </p>

                <?php if (
                    $order['phuong_thuc_thanh_toan'] === 'bank_transfer'
                    && $order['trang_thai_thanh_toan'] === 'unpaid'
                ): ?>

                    <h3>Quét mã QR Vietcombank để chuyển khoản</h3>

                    <p>
                        Vui lòng chuyển đúng số tiền và giữ nguyên
                        nội dung chuyển khoản là mã đơn hàng.
                    </p>

                    <div class="payment-qr">
                        <img
                            src="<?= lm_checkout_h($qrUrl) ?>"
                            alt="Mã QR thanh toán đơn hàng La Muse"
                            style="display:block;width:100%;max-width:300px;height:auto;margin:20px auto;"
                        >

                        <p>
                            Ngân hàng:
                            <strong>Vietcombank</strong>
                        </p>

                        <p>
                            Chủ tài khoản:
                            <strong>LA MUSE</strong>
                        </p>

                        <p>
                            Số tài khoản:
                            <strong><?= lm_checkout_h($bankAccount) ?></strong>
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText('<?= lm_checkout_h($bankAccount) ?>')"
                            >
                               Sao chép
                            </button>
                        </p>

                        <p>
                            Số tiền cần chuyển:
                            <strong>
                                <?= lm_checkout_money($order['thanh_tien']) ?>
                            </strong>
                            
                        </p>

                        <p>
                            Nội dung chuyển khoản:
                            <strong>
                                <?= lm_checkout_h($order['ma_don_hang']) ?>
                            </strong>
                            
                        </p>

                        <p>
                            <strong>Trạng thái:</strong>
                            Chờ quản trị viên xác nhận thanh toán.
                        </p>
                    </div>

                <?php elseif (
                    $order['trang_thai_thanh_toan'] === 'paid'
                ): ?>

                    <p>
                        Đơn hàng đã được xác nhận thanh toán.
                    </p>

                <?php elseif (
                    $order['phuong_thuc_thanh_toan'] === 'cod'
                ): ?>

                    <p>
                        La Muse đã tiếp nhận đơn hàng COD.
                        Quý khách thanh toán khi nhận hàng.
                    </p>

                <?php else: ?>

                    <p>
                        Trạng thái thanh toán:
                        <?= lm_checkout_h($order['trang_thai_thanh_toan']) ?>
                    </p>

                <?php endif; ?>

                <h3>Chi tiết sản phẩm</h3>

                <?php foreach ($orderItems as $item): ?>
                    <div class="summary-product">
                        <div class="product-info">
                            <h3>
                                <?= lm_checkout_h($item['ten_san_pham']) ?>
                            </h3>

                            <p>
                                Mã:
                                <?= lm_checkout_h($item['ma_san_pham'] ?? '') ?>
                            </p>

                            <p>
                                Màu: <?= lm_checkout_h($item['ten_mau'] ?? '') ?>
                                · Size: <?= lm_checkout_h($item['ten_size'] ?? '') ?>
                            </p>

                            <p>
                                Số lượng: <?= (int) $item['so_luong'] ?>
                            </p>

                            <strong>
                                <?= lm_checkout_money($item['thanh_tien']) ?>
                            </strong>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p>
                    <a href="index.php" class="back-to-cart">
                        Tiếp tục mua sắm
                    </a>
                </p>
            </section>

            <aside class="order-summary">
                <h2>Đơn hàng của quý cô</h2>

                <div class="summary-row">
                    <span>Tạm tính</span>
                    <span>
                        <?= lm_checkout_money($order['tong_tien']) ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span>Phí vận chuyển</span>
                    <span>
                        <?= lm_checkout_money($order['phi_van_chuyen']) ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span>Giảm giá</span>
                    <span>
                        <?= lm_checkout_money($order['giam_gia']) ?>
                    </span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row total-row">
                    <strong>Tổng thanh toán</strong>
                    <strong>
                        <?= lm_checkout_money($order['thanh_tien']) ?>
                    </strong>
                </div>

                <p>
                    Mã đơn hàng:
                    <strong>
                        <?= lm_checkout_h($order['ma_don_hang']) ?>
                    </strong>
                </p>
            </aside>

        <?php else: ?>

            <section class="checkout-form">
                <h2>Thông tin nhận hàng</h2>

                <form action="thanhtoan.php" method="post">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= lm_checkout_h($csrfToken) ?>"
                    >

                    <div class="form-group">
                        <label for="fullname">Họ và tên</label>
                        <input
                            type="text"
                            id="fullname"
                            name="fullname"
                            maxlength="100"
                            placeholder="Nhập họ và tên"
                            autocomplete="name"
                            value="<?= lm_checkout_h($formValues['fullname']) ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                placeholder="Nhập số điện thoại"
                                autocomplete="tel"
                                value="<?= lm_checkout_h($formValues['phone']) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="255"
                                placeholder="Nhập email"
                                autocomplete="email"
                                value="<?= lm_checkout_h($formValues['email']) ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Địa chỉ nhận hàng</label>
                        <input
                            type="text"
                            id="address"
                            name="address"
                            maxlength="255"
                            placeholder="Số nhà, tên đường..."
                            autocomplete="street-address"
                            value="<?= lm_checkout_h($formValues['address']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="note">Ghi chú đơn hàng</label>
                        <textarea
                            id="note"
                            name="note"
                            rows="3"
                            maxlength="5000"
                            placeholder="Lời nhắn dành cho La Muse (nếu có)"
                        ><?= lm_checkout_h($formValues['note']) ?></textarea>
                    </div>

                    <h2 class="section-heading">
                        Phương thức vận chuyển
                    </h2>

                    <label class="option-card">
                        <input
                            type="radio"
                            name="shipping"
                            value="standard"
                            checked
                        >

                        <span>
                            <strong>Giao hàng tiêu chuẩn</strong>
                            <small>Ngày giao dự kiến từ 3-5 ngày</small>
                        </span>
                    </label>

                    <h2 class="section-heading">
                        Phương thức thanh toán
                    </h2>

                    <label class="option-card">
                        <input
                            type="radio"
                            name="payment"
                            value="cod"
                            <?= $formValues['payment'] === 'cod' ? 'checked' : '' ?>
                        >

                        <span>
                            <strong>Thanh toán khi nhận hàng (COD)</strong>
                            <small>Thanh toán sau khi nhận hàng</small>
                        </span>
                    </label>

                    <label class="option-card">
                        <input
                            type="radio"
                            name="payment"
                            value="bank"
                            <?= $formValues['payment'] === 'bank' ? 'checked' : '' ?>
                        >

                        <span class="option-content">
                            <strong>Chuyển khoản qua tài khoản ngân hàng</strong>
                            <small>
                                Quét QR Vietcombank và chuyển đúng số tiền
                                cùng nội dung mã đơn hàng.
                            </small>
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="checkout-button"
                        <?= $totalQuantity === 0 ? 'disabled' : '' ?>
                    >
                        XÁC NHẬN ĐẶT HÀNG
                    </button>
                </form>
            </section>

            <aside class="order-summary">
                <h2>Đơn hàng của quý cô</h2>

                <?php if ($totalQuantity === 0): ?>

                    <div class="summary-empty">
                        <p>Giỏ hàng của nàng vẫn chưa có sản phẩm.</p>

                        <a href="cart.php" class="back-to-cart">
                            Quay lại giỏ hàng
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($cart as $key => $row): ?>
                        <?php
                        if (!is_array($row)) {
                            continue;
                        }

                        $name = (string) ($row['name'] ?? 'Sản phẩm La Muse');
                        $image = trim((string) ($row['image'] ?? ''));
                        $sku = (string) ($row['sku'] ?? '');
                        $color = (string) ($row['color_name'] ?? '');
                        $size = (string) ($row['size_name'] ?? '');
                        $price = max(0, (float) ($row['price'] ?? 0));
                        $quantity = max(0, (int) ($row['quantity'] ?? 0));
                        $lineTotal = $price * $quantity;
                        ?>

                        <div class="summary-product">
                            <?php if ($image !== ''): ?>
                                <img
                                    src="<?= lm_checkout_h($image) ?>"
                                    alt="<?= lm_checkout_h($name) ?>"
                                    class="summary-product-image"
                                    loading="lazy"
                                    style="width:76px;height:92px;object-fit:cover;flex-shrink:0;"
                                >
                            <?php else: ?>
                                <div class="product-placeholder">LM</div>
                            <?php endif; ?>

                            <div class="product-info">
                                <h3><?= lm_checkout_h($name) ?></h3>

                                <?php if ($sku !== ''): ?>
                                    <p>Mã: <?= lm_checkout_h($sku) ?></p>
                                <?php endif; ?>

                                <p>
                                    Màu: <?= lm_checkout_h($color) ?>
                                    · Size: <?= lm_checkout_h($size) ?>
                                </p>

                                <p>Số lượng: <?= $quantity ?></p>

                                <span><?= lm_checkout_money($price) ?></span>

                                <p class="summary-line-total">
                                    Thành tiền:
                                    <strong>
                                        <?= lm_checkout_money($lineTotal) ?>
                                    </strong>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="summary-divider"></div>

                    <div class="summary-row">
                        <span>Tạm tính (<?= $totalQuantity ?> sản phẩm)</span>
                        <span><?= lm_checkout_money($subtotal) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Phí vận chuyển</span>
                        <span><?= lm_checkout_money($shippingFee) ?></span>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-row total-row">
                        <strong>Tổng thanh toán</strong>
                        <strong><?= lm_checkout_money($grandTotal) ?></strong>
                    </div>

                    <p class="summary-note">
                        Cảm ơn nàng đã lựa chọn La Muse.
                    </p>

                <?php endif; ?>
            </aside>

        <?php endif; ?>

    </div>
</main>

</body>
</html>
