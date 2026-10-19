<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Phương thức không được hỗ trợ.']); exit;
}
$data = json_decode(file_get_contents('php://input') ?: '', true);
$productId = filter_var(is_array($data) ? ($data['product_id'] ?? null) : null, FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(422); echo json_encode(['success'=>false,'message'=>'Sản phẩm không hợp lệ.']); exit;
}
$stmt = $pdo->prepare("SELECT id FROM sanpham WHERE id = ? AND trang_thai = 'dang_ban' LIMIT 1");
$stmt->execute([$productId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404); echo json_encode(['success'=>false,'message'=>'Không tìm thấy sản phẩm.']); exit;
}
if (!isset($_SESSION['lm_wishlist']) || !is_array($_SESSION['lm_wishlist'])) $_SESSION['lm_wishlist'] = [];
$ids = array_map('intval', $_SESSION['lm_wishlist']);
$active = !in_array((int)$productId, $ids, true);
if ($active) $ids[] = (int)$productId;
else $ids = array_values(array_filter($ids, static fn($id) => $id !== (int)$productId));
$_SESSION['lm_wishlist'] = array_values(array_unique($ids));
echo json_encode(['success'=>true,'active'=>$active,'message'=>$active ? 'Đã thêm vào danh sách yêu thích.' : 'Đã bỏ khỏi danh sách yêu thích.'], JSON_UNESCAPED_UNICODE);
