<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

function lm_cart_action_reply(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lm_cart_action_reply(405, ['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
}
$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    lm_cart_action_reply(400, ['success' => false, 'message' => 'Dữ liệu gửi lên không hợp lệ.']);
}

$action = $data['action'] ?? 'add';
$variantId = filter_var($data['variant_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
if ($variantId === false || $variantId === null || $variantId < 1 || $quantity === false || $quantity === null || $quantity < 1 || $quantity > 99) {
    lm_cart_action_reply(422, ['success' => false, 'message' => 'Vui lòng chọn biến thể và số lượng hợp lệ.']);
}
if (!in_array($action, ['add', 'buy_now'], true)) {
    lm_cart_action_reply(422, ['success' => false, 'message' => 'Hành động không hợp lệ.']);
}

$stmt = $pdo->prepare("
    SELECT bt.id, bt.sku, bt.san_pham_id, bt.so_luong_ton, bt.gia_ban,
           bt.mau_sac_id, bt.kich_thuoc_id,
           sp.ten_san_pham, sp.ma_san_pham, sp.gia_niem_yet, sp.gia_khuyen_mai, sp.anh_dai_dien,
           ms.ten_mau, kt.ten_size
    FROM bien_the_sanpham bt
    JOIN sanpham sp ON sp.id = bt.san_pham_id
    JOIN danhmuc dm ON dm.id = sp.danh_muc_id
    JOIN mausac ms ON ms.id = bt.mau_sac_id
    JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
    WHERE bt.id = :id AND bt.trang_thai = 1
      AND sp.trang_thai = 'dang_ban' AND dm.trang_thai = 1
      AND ms.trang_thai = 1 AND kt.trang_thai = 1
    LIMIT 1
");
$stmt->execute(['id' => $variantId]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$v) {
    lm_cart_action_reply(404, ['success' => false, 'message' => 'Biến thể không còn khả dụng.']);
}
if ((int)$v['so_luong_ton'] < 1) {
    lm_cart_action_reply(409, ['success' => false, 'message' => 'Biến thể này đã hết hàng.']);
}

// Chỉ khách đã đăng nhập mới được thêm giỏ hàng hoặc mua ngay.
$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId === null || $userId < 1) {
    $next = 'chitietsanpham.php?id=' . (int)$v['san_pham_id'];
    lm_cart_action_reply(401, [
        'success' => false,
        'message' => 'Nàng hãy đăng nhập để tiếp tục mua sắm nhé.',
        'redirect' => 'login.php?next=' . urlencode($next),
    ]);
}

$userStmt = $pdo->prepare("SELECT status FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$userStatus = $userStmt->fetchColumn();
if ($userStatus !== 'active') {
    lm_cart_action_reply(403, ['success' => false, 'message' => 'Tài khoản hiện không thể mua hàng.']);
}

$price = ((float)($v['gia_ban'] ?? 0) > 0)
    ? (float)$v['gia_ban']
    : (((float)($v['gia_khuyen_mai'] ?? 0) > 0 && (float)$v['gia_khuyen_mai'] < (float)$v['gia_niem_yet'])
        ? (float)$v['gia_khuyen_mai'] : (float)$v['gia_niem_yet']);

if (!isset($_SESSION['lm_cart']) || !is_array($_SESSION['lm_cart'])) {
    $_SESSION['lm_cart'] = [];
}
$cart =& $_SESSION['lm_cart'];
$existingQty = isset($cart[$variantId]) ? (int)($cart[$variantId]['quantity'] ?? 0) : 0;
$newQty = $action === 'buy_now' ? (int)$quantity : $existingQty + (int)$quantity;
if ($newQty > (int)$v['so_luong_ton']) {
    lm_cart_action_reply(409, ['success' => false, 'message' => 'Số lượng yêu cầu vượt quá tồn kho hiện tại.']);
}

$freshRow = [
    'variant_id' => (int)$v['id'],
    'product_id' => (int)$v['san_pham_id'],
    'sku' => (string)$v['sku'],
    'name' => (string)$v['ten_san_pham'],
    'product_code' => (string)$v['ma_san_pham'],
    'color_id' => (int)$v['mau_sac_id'],
    'color_name' => (string)$v['ten_mau'],
    'size_id' => (int)$v['kich_thuoc_id'],
    'size_name' => (string)$v['ten_size'],
    'price' => $price,
    'quantity' => (int)$quantity,
    'stock' => (int)$v['so_luong_ton'],
    'image' => (string)($v['anh_dai_dien'] ?? ''),
];

if ($action === 'buy_now') {
    // Mua ngay dùng một dòng tạm riêng, không ghi đè số lượng cùng biến thể trong giỏ thường.
    $freshRow['quantity'] = (int)$quantity;
    $_SESSION['lm_buy_now'] = $freshRow;
    $_SESSION['lm_checkout_mode'] = 'buy_now';
} else {
    $cart[$variantId] = $freshRow;
    unset($_SESSION['lm_checkout_mode'], $_SESSION['lm_buy_now']);
    $selected = $_SESSION['lm_cart_selected'] ?? array_keys($cart);
    if (!is_array($selected)) {
        $selected = [];
    }
    $selected = array_map('intval', $selected);
    if (!in_array((int)$variantId, $selected, true)) {
        $selected[] = (int)$variantId;
    }
    $_SESSION['lm_cart_selected'] = array_values(array_unique($selected));
}

$cartCount = array_sum(array_map(static fn($row): int => max(0, (int)($row['quantity'] ?? 0)), $cart));
lm_cart_action_reply(200, [
    'success' => true,
    'message' => $action === 'buy_now' ? 'Đang chuyển nàng đến bước thanh toán.' : 'Đã thêm sản phẩm vào giỏ hàng.',
    'cart_count' => $cartCount,
    'redirect' => $action === 'buy_now' ? 'thanhtoan.php' : null,
]);
