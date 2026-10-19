<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Quản lý đơn hàng';
$active = 'orders';

function order_status_label(string $status): string {
    return [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy',
    ][$status] ?? $status;
}

function payment_status_label(string $status): string {
    return [
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'refunded' => 'Đã hoàn tiền',
    ][$status] ?? $status;
}

$orderStatuses = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$paymentStatuses = ['unpaid', 'paid', 'refunded'];
$keyword = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$payment = (string)($_GET['payment'] ?? '');
$viewId = max(0, (int)($_GET['view'] ?? 0));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$message = '';
$error = '';

if (!in_array($status, array_merge([''], $orderStatuses), true)) $status = '';
if (!in_array($payment, array_merge([''], $paymentStatuses), true)) $payment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)csrf_token();

    if ($postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $error = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } else {
        $orderId = max(0, (int)($_POST['order_id'] ?? 0));
        $newStatus = (string)($_POST['trang_thai_don_hang'] ?? '');
        $newPayment = (string)($_POST['trang_thai_thanh_toan'] ?? '');

        if ($orderId < 1 || !in_array($newStatus, $orderStatuses, true) || !in_array($newPayment, $paymentStatuses, true)) {
            $error = 'Dữ liệu cập nhật không hợp lệ.';
        } else {
            $check = $pdo->prepare('SELECT id FROM don_hang WHERE id = :id');
            $check->execute(['id' => $orderId]);

            if (!$check->fetchColumn()) {
                $error = 'Không tìm thấy đơn hàng cần cập nhật.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE don_hang
                     SET trang_thai_don_hang = :order_status,
                         trang_thai_thanh_toan = :payment_status
                     WHERE id = :id'
                );
                $update->execute([
                    'order_status' => $newStatus,
                    'payment_status' => $newPayment,
                    'id' => $orderId,
                ]);

                header('Location: orders.php?updated=1' . ($viewId ? '&view=' . $viewId : ''));
                exit;
            }
        }
    }
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Đã cập nhật đơn hàng thành công.';
}

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(dh.ma_don_hang LIKE :code OR dh.ten_nguoi_nhan LIKE :recipient OR dh.so_dien_thoai LIKE :phone OR dh.email LIKE :email)';
    $like = '%' . $keyword . '%';
    $params['code'] = $like;
    $params['recipient'] = $like;
    $params['phone'] = $like;
    $params['email'] = $like;
}
if ($status !== '') {
    $where[] = 'dh.trang_thai_don_hang = :status';
    $params['status'] = $status;
}
if ($payment !== '') {
    $where[] = 'dh.trang_thai_thanh_toan = :payment';
    $params['payment'] = $payment;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM don_hang')->fetchColumn();
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'pending'")->fetchColumn();
$shippingOrders = (int)$pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'shipping'")->fetchColumn();
$revenue = (float)$pdo->query("SELECT COALESCE(SUM(thanh_tien), 0) FROM don_hang WHERE trang_thai_don_hang = 'delivered'")->fetchColumn();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM don_hang dh' . $whereSql);
$countStmt->execute($params);
$filteredCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT dh.id, dh.ma_don_hang, dh.ten_nguoi_nhan, dh.so_dien_thoai,
                   dh.tong_tien, dh.phi_van_chuyen, dh.giam_gia, dh.thanh_tien,
                   dh.phuong_thuc_thanh_toan, dh.trang_thai_thanh_toan,
                   dh.trang_thai_don_hang, dh.ngay_dat
            FROM don_hang dh' . $whereSql . '
            ORDER BY dh.ngay_dat DESC, dh.id DESC
            LIMIT :limit OFFSET :offset';
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$orders = $listStmt->fetchAll();

$detailOrder = null;
$orderItems = [];
if ($viewId > 0) {
    $detailStmt = $pdo->prepare(
        'SELECT dh.*, u.full_name AS account_name
         FROM don_hang dh
         LEFT JOIN users u ON u.id = dh.user_id
         WHERE dh.id = :id'
    );
    $detailStmt->execute(['id' => $viewId]);
    $detailOrder = $detailStmt->fetch() ?: null;

    if ($detailOrder) {
        $itemsStmt = $pdo->prepare(
            'SELECT ten_san_pham, ma_san_pham, ten_mau, ten_size, don_gia, so_luong, thanh_tien
             FROM chi_tiet_don_hang
             WHERE don_hang_id = :id
             ORDER BY id ASC'
        );
        $itemsStmt->execute(['id' => $viewId]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

require __DIR__ . '/admin-header.php';
?>

<style>
.orders-page{display:grid;gap:20px;color:#342720}
.orders-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.orders-stat,.orders-panel{background:#fff;border:1px solid #eee8e5;border-radius:14px;padding:20px}
.orders-stat span{display:block;color:#777;font-size:14px}
.orders-stat strong{display:block;font-size:25px;margin-top:8px}
.orders-filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.orders-filters input,.orders-filters select,.orders-edit select{padding:10px 12px;border:1px solid #ddd;border-radius:8px;background:#fff;color:#342720}
.orders-filters input{min-width:240px;flex:1}
.orders-btn{display:inline-block;border:0;border-radius:8px;padding:9px 12px;cursor:pointer;text-decoration:none;font-size:13px;white-space:nowrap}
.orders-btn-primary{background:#342720;color:#fff}
.orders-btn-light{background:#f5f0eb;color:#342720}
.orders-btn-save{background:#e8f2e5;color:#345b2d}
.orders-table-wrap{overflow-x:auto}
.orders-table{width:100%;border-collapse:collapse}
.orders-table th,.orders-table td{padding:13px 11px;border-bottom:1px solid #eee;text-align:left;font-size:13px;vertical-align:top}
.orders-table th{color:#777;font-weight:600;white-space:nowrap}
.orders-table td{white-space:nowrap}
.orders-muted{color:#777;font-size:12px;margin-top:4px}
.orders-pill{display:inline-block;border-radius:20px;padding:5px 9px;font-size:12px;white-space:nowrap}
.orders-pill.pending{background:#fff3d9;color:#8a5a00}
.orders-pill.confirmed{background:#e8eefc;color:#31549b}
.orders-pill.shipping{background:#e8f2ff;color:#235c91}
.orders-pill.delivered,.orders-pill.paid{background:#e8f6ed;color:#216b3b}
.orders-pill.cancelled,.orders-pill.unpaid{background:#fff0e8;color:#9a451f}
.orders-pill.refunded{background:#f0e8fa;color:#67408d}
.orders-alert{padding:12px 14px;border-radius:9px;background:#e8f6ed;color:#216b3b}
.orders-alert.error{background:#fff0e8;color:#9a451f}
.orders-pagination{display:flex;gap:8px;justify-content:flex-end;align-items:center;margin-top:18px;flex-wrap:wrap}
.orders-pagination a,.orders-pagination span{padding:8px 11px;border:1px solid #eee;border-radius:7px;text-decoration:none;color:inherit}
.orders-pagination .current{background:#342720;color:white}
.orders-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.orders-detail-item{padding:10px 0;border-bottom:1px solid #f0ebe7}
.orders-detail-item span{display:block;color:#777;font-size:12px;margin-bottom:4px}
.orders-edit{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.orders-edit select{min-width:145px}
.orders-empty{text-align:center;padding:25px;color:#777}
@media(max-width:900px){.orders-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.orders-stats,.orders-detail-grid{grid-template-columns:1fr}.orders-panel,.orders-stat{padding:15px}}
</style>

<div class="orders-page">
    <?php if ($message !== ''): ?><div class="orders-alert"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="orders-alert error"><?= e($error) ?></div><?php endif; ?>

    <div class="orders-stats">
        <div class="orders-stat"><span>Tổng đơn hàng</span><strong><?= number_format($totalOrders) ?></strong></div>
        <div class="orders-stat"><span>Chờ xác nhận</span><strong><?= number_format($pendingOrders) ?></strong></div>
        <div class="orders-stat"><span>Đang giao</span><strong><?= number_format($shippingOrders) ?></strong></div>
        <div class="orders-stat"><span>Doanh thu đơn đã giao</span><strong><?= number_format($revenue, 0, ',', '.') ?>đ</strong></div>
    </div>

    <?php if ($detailOrder): ?>
    <section class="orders-panel" id="order-detail">
        <h2 style="margin:0 0 16px;font-size:20px">Chi tiết đơn <?= e($detailOrder['ma_don_hang']) ?></h2>
        <div class="orders-detail-grid">
            <div>
                <div class="orders-detail-item"><span>Người nhận</span><strong><?= e($detailOrder['ten_nguoi_nhan']) ?></strong></div>
                <div class="orders-detail-item"><span>Số điện thoại</span><?= e($detailOrder['so_dien_thoai']) ?></div>
                <div class="orders-detail-item"><span>Email</span><?= e($detailOrder['email'] ?: '—') ?></div>
                <div class="orders-detail-item"><span>Tài khoản đặt hàng</span><?= e($detailOrder['account_name'] ?: '—') ?></div>
            </div>
            <div>
                <div class="orders-detail-item"><span>Địa chỉ giao hàng</span><?= e($detailOrder['dia_chi']) ?>, <?= e($detailOrder['phuong_xa'] ?: '') ?>, <?= e($detailOrder['quan_huyen'] ?: '') ?>, <?= e($detailOrder['tinh_thanh'] ?: '') ?></div>
                <div class="orders-detail-item"><span>Ngày đặt</span><?= e(date('d/m/Y H:i', strtotime($detailOrder['ngay_dat']))) ?></div>
                <div class="orders-detail-item"><span>Thanh toán</span><?= e($detailOrder['phuong_thuc_thanh_toan'] === 'cod' ? 'Thanh toán khi nhận hàng (COD)' : 'Chuyển khoản') ?></div>
                <div class="orders-detail-item"><span>Ghi chú</span><?= nl2br(e($detailOrder['ghi_chu'] ?: '—')) ?></div>
            </div>
        </div>

        <h3 style="margin:22px 0 10px;font-size:16px">Sản phẩm trong đơn</h3>
        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead><tr><th>Sản phẩm</th><th>Mã SP</th><th>Màu</th><th>Size</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead>
                <tbody>
                <?php if (!$orderItems): ?>
                    <tr><td colspan="7" class="orders-empty">Đơn hàng chưa có chi tiết sản phẩm.</td></tr>
                <?php else: foreach ($orderItems as $item): ?>
                    <tr>
                        <td><?= e($item['ten_san_pham']) ?></td><td><?= e($item['ma_san_pham'] ?: '—') ?></td>
                        <td><?= e($item['ten_mau'] ?: '—') ?></td><td><?= e($item['ten_size'] ?: '—') ?></td>
                        <td><?= number_format((float)$item['don_gia'], 0, ',', '.') ?>đ</td>
                        <td><?= (int)$item['so_luong'] ?></td><td><?= number_format((float)$item['thanh_tien'], 0, ',', '.') ?>đ</td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;text-align:right;line-height:1.9">
            <div>Tạm tính: <strong><?= number_format((float)$detailOrder['tong_tien'], 0, ',', '.') ?>đ</strong></div>
            <div>Phí vận chuyển: <strong><?= number_format((float)$detailOrder['phi_van_chuyen'], 0, ',', '.') ?>đ</strong></div>
            <div>Giảm giá: <strong><?= number_format((float)$detailOrder['giam_gia'], 0, ',', '.') ?>đ</strong></div>
            <div style="font-size:18px">Tổng thanh toán: <strong><?= number_format((float)$detailOrder['thanh_tien'], 0, ',', '.') ?>đ</strong></div>
        </div>
        <form method="post" class="orders-edit" style="margin-top:18px">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="order_id" value="<?= (int)$detailOrder['id'] ?>">
            <label for="order-status">Trạng thái đơn:</label>
            <select id="order-status" name="trang_thai_don_hang">
                <?php foreach ($orderStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $detailOrder['trang_thai_don_hang'] === $option ? 'selected' : '' ?>><?= e(order_status_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="payment-status">Thanh toán:</label>
            <select id="payment-status" name="trang_thai_thanh_toan">
                <?php foreach ($paymentStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $detailOrder['trang_thai_thanh_toan'] === $option ? 'selected' : '' ?>><?= e(payment_status_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="orders-btn orders-btn-save" type="submit" onclick="return confirm('Bạn có chắc muốn cập nhật đơn hàng này?')">Lưu thay đổi</button>
            <a class="orders-btn orders-btn-light" href="orders.php">Đóng chi tiết</a>
        </form>
    </section>
    <?php elseif ($viewId > 0): ?>
        <div class="orders-alert error">Không tìm thấy đơn hàng này.</div>
    <?php endif; ?>

    <section class="orders-panel">
        <h2 style="margin:0 0 16px;font-size:20px">Danh sách đơn hàng</h2>
        <form method="get" class="orders-filters">
            <input type="search" name="q" placeholder="Tìm mã đơn, người nhận, SĐT, email..." value="<?= e($keyword) ?>">
            <select name="status">
                <option value="">Tất cả trạng thái đơn</option>
                <?php foreach ($orderStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(order_status_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="payment">
                <option value="">Tất cả thanh toán</option>
                <?php foreach ($paymentStatuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $payment === $option ? 'selected' : '' ?>><?= e(payment_status_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="orders-btn orders-btn-primary" type="submit">Tìm kiếm</button>
            <a class="orders-btn orders-btn-light" href="orders.php">Xóa bộ lọc</a>
        </form>

        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead><tr><th>Mã đơn</th><th>Khách nhận</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái đơn</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="7" class="orders-empty">Chưa có đơn hàng phù hợp.</td></tr>
                <?php else: foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?= e($order['ma_don_hang']) ?></strong></td>
                        <td><?= e($order['ten_nguoi_nhan']) ?><div class="orders-muted"><?= e($order['so_dien_thoai']) ?></div></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($order['ngay_dat']))) ?></td>
                        <td><strong><?= number_format((float)$order['thanh_tien'], 0, ',', '.') ?>đ</strong></td>
                        <td><span class="orders-pill <?= e($order['trang_thai_thanh_toan']) ?>"><?= e(payment_status_label($order['trang_thai_thanh_toan'])) ?></span></td>
                        <td><span class="orders-pill <?= e($order['trang_thai_don_hang']) ?>"><?= e(order_status_label($order['trang_thai_don_hang'])) ?></span></td>
                        <td><a class="orders-btn orders-btn-primary" href="?<?= e(http_build_query(['q'=>$keyword,'status'=>$status,'payment'=>$payment,'page'=>$page,'view'=>(int)$order['id']])) ?>">Xem chi tiết</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="orders-pagination">
            <span><?= $filteredCount > 0 ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $filteredCount) ?> / <?= $filteredCount ?> đơn hàng</span>
            <?php if ($page > 1): ?><a href="?<?= e(http_build_query(['q'=>$keyword,'status'=>$status,'payment'=>$payment,'page'=>$page-1])) ?>">← Trước</a><?php endif; ?>
            <span class="current"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?><a href="?<?= e(http_build_query(['q'=>$keyword,'status'=>$status,'payment'=>$payment,'page'=>$page+1])) ?>">Sau →</a><?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/admin-footer.php'; ?>
