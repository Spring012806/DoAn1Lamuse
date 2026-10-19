<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

function cart_reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cart_reply(405, ['success'=>false,'message'=>'Phương thức không được hỗ trợ.']);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) cart_reply(400, ['success'=>false,'message'=>'Dữ liệu gửi lên không hợp lệ.']);

$action = $data['action'] ?? 'add';
$variantId = filter_var($data['variant_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
if (!$variantId || $variantId < 1 || !$quantity || $quantity < 1 || $quantity > 99) {
    cart_reply(422, ['success'=>false,'message'=>'Vui lòng chọn biến thể và số lượng hợp lệ.']);
}
if (!in_array($action, ['add','buy_now'], true)) cart_reply(422, ['success'=>false,'message'=>'Hành động không hợp lệ.']);

$stmt = $pdo->prepare("
    SELECT bt.id, bt.sku, bt.san_pham_id, bt.so_luong_ton, bt.gia_ban,
           bt.mau_sac_id, bt.kich_thuoc_id,
           sp.ten_san_pham, sp.ma_san_pham, sp.gia_niem_yet, sp.gia_khuyen_mai,
           ms.ten_mau, kt.ten_size
    FROM bien_the_sanpham bt
    JOIN sanpham sp ON sp.id = bt.san_pham_id
    JOIN mausac ms ON ms.id = bt.mau_sac_id
    JOIN kichthuoc kt ON kt.id = bt.kich_thuoc_id
    JOIN danhmuc dm ON dm.id = sp.danh_muc_id
    WHERE bt.id = :id AND bt.trang_thai = 1
      AND sp.trang_thai = 'dang_ban' AND dm.trang_thai = 1
      AND ms.trang_thai = 1 AND kt.trang_thai = 1
    LIMIT 1
");
$stmt->execute(['id'=>$variantId]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$v) cart_reply(404, ['success'=>false,'message'=>'Biến thể không còn khả dụng.']);
if ((int)$v['so_luong_ton'] < 1) cart_reply(409, ['success'=>false,'message'=>'Sản phẩm đã hết hàng.']);

$price = ($v['gia_ban'] !== null && (float)$v['gia_ban'] > 0)
    ? (float)$v['gia_ban']
    : (($v['gia_khuyen_mai'] !== null && (float)$v['gia_khuyen_mai'] > 0 && (float)$v['gia_khuyen_mai'] < (float)$v['gia_niem_yet'])
        ? (float)$v['gia_khuyen_mai'] : (float)$v['gia_niem_yet']);

if (!isset($_SESSION['lm_cart']) || !is_array($_SESSION['lm_cart'])) $_SESSION['lm_cart'] = [];
$cart =& $_SESSION['lm_cart'];
$existingQty = isset($cart[$variantId]) ? (int)$cart[$variantId]['quantity'] : 0;
$newQty = $action === 'buy_now' ? $quantity : $existingQty + $quantity;
if ($newQty > (int)$v['so_luong_ton']) {
    cart_reply(409, ['success'=>false,'message'=>'Số lượng yêu cầu vượt quá tồn kho hiện tại.']);
}
$cart[$variantId] = [
    'variant_id'=>(int)$v['id'], 'product_id'=>(int)$v['san_pham_id'],
    'sku'=>$v['sku'], 'name'=>$v['ten_san_pham'], 'product_code'=>$v['ma_san_pham'],
    'color_id'=>(int)$v['mau_sac_id'], 'color_name'=>$v['ten_mau'],
    'size_id'=>(int)$v['kich_thuoc_id'], 'size_name'=>$v['ten_size'],
    'price'=>$price, 'quantity'=>$newQty
];

cart_reply(200, [
    'success'=>true,
    'message'=>$action === 'buy_now' ? 'Đã chuẩn bị sản phẩm để mua ngay.' : 'Đã thêm sản phẩm vào giỏ hàng.',
    'cart_count'=>array_sum(array_map(static fn($row)=>(int)$row['quantity'], $cart)),
    'redirect'=>$action === 'buy_now' ? 'cart.php' : null
]);
