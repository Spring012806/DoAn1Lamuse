<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Báo cáo doanh thu';
$active = 'revenue';

function lm_report_money($value): string {
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

$dateFrom = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['from'])
    ? (string)$_GET['from'] : $defaultFrom;
$dateTo = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['to'])
    ? (string)$_GET['to'] : $defaultTo;

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
$start = $dateFrom . ' 00:00:00';
$endExclusive = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

/*
 * Quy ước bản báo cáo đầu tiên:
 * - Doanh thu đơn đã giao: đơn delivered, không bị hủy và chưa đánh dấu refunded.
 * - Tiền đã thu: các đơn thuộc nhóm trên có trạng thái thanh toán paid.
 * - Vì schema hiện tại không lưu lịch sử hoàn tiền một phần, báo cáo chưa thể trừ
 *   chính xác khoản hoàn một phần. Trạng thái refunded được xem là hoàn toàn bộ.
 */
$revenueWhere = "ngay_dat >= :start AND ngay_dat < :end AND trang_thai_don_hang = 'delivered' AND trang_thai_thanh_toan <> 'refunded'";
$summaryStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(thanh_tien), 0) AS revenue,
        COUNT(*) AS delivered_orders,
        COALESCE(AVG(thanh_tien), 0) AS average_order,
        COALESCE(SUM(CASE WHEN trang_thai_thanh_toan = 'paid' THEN thanh_tien ELSE 0 END), 0) AS collected,
        COALESCE(SUM(CASE WHEN trang_thai_thanh_toan = 'unpaid' THEN thanh_tien ELSE 0 END), 0) AS unpaid_delivered
    FROM don_hang
    WHERE $revenueWhere
");
$summaryStmt->execute([':start' => $start, ':end' => $endExclusive]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$pendingStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM don_hang
    WHERE ngay_dat >= :start AND ngay_dat < :end
      AND trang_thai_don_hang IN ('pending','confirmed','shipping')
");
$pendingStmt->execute([':start' => $start, ':end' => $endExclusive]);
$pendingOrders = (int)$pendingStmt->fetchColumn();

$dailyStmt = $pdo->prepare("
    SELECT DATE(ngay_dat) AS day, COALESCE(SUM(thanh_tien),0) AS revenue
    FROM don_hang
    WHERE $revenueWhere
    GROUP BY DATE(ngay_dat)
    ORDER BY day ASC
");
$dailyStmt->execute([':start' => $start, ':end' => $endExclusive]);
$dailyRows = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
$dailyMap = [];
foreach ($dailyRows as $row) {
    $dailyMap[$row['day']] = (float)$row['revenue'];
}
$chartLabels = [];
$chartValues = [];
$periodStart = new DateTimeImmutable($dateFrom);
$periodEnd = new DateTimeImmutable($dateTo);
$days = (int)$periodStart->diff($periodEnd)->days;
if ($days <= 62) {
    for ($d = $periodStart; $d <= $periodEnd; $d = $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        $chartLabels[] = $d->format('d/m');
        $chartValues[] = $dailyMap[$key] ?? 0;
    }
} else {
    $monthlyStmt = $pdo->prepare("
        SELECT DATE_FORMAT(ngay_dat, '%Y-%m') AS month_key, COALESCE(SUM(thanh_tien),0) AS revenue
        FROM don_hang
        WHERE $revenueWhere
        GROUP BY DATE_FORMAT(ngay_dat, '%Y-%m')
        ORDER BY month_key ASC
    ");
    $monthlyStmt->execute([':start' => $start, ':end' => $endExclusive]);
    $monthlyRows = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    $monthlyMap = [];
    foreach ($monthlyRows as $row) $monthlyMap[$row['month_key']] = (float)$row['revenue'];
    $monthCursor = $periodStart->modify('first day of this month');
    $monthEnd = $periodEnd->modify('first day of this month');
    while ($monthCursor <= $monthEnd) {
        $key = $monthCursor->format('Y-m');
        $chartLabels[] = $monthCursor->format('m/Y');
        $chartValues[] = $monthlyMap[$key] ?? 0;
        $monthCursor = $monthCursor->modify('+1 month');
    }
}

$productStmt = $pdo->prepare("
    SELECT ct.ten_san_pham, COALESCE(SUM(ct.so_luong),0) AS quantity,
           COALESCE(SUM(ct.thanh_tien),0) AS revenue
    FROM chi_tiet_don_hang ct
    INNER JOIN don_hang dh ON dh.id = ct.don_hang_id
    WHERE dh.ngay_dat >= :start AND dh.ngay_dat < :end
      AND dh.trang_thai_don_hang = 'delivered'
      AND dh.trang_thai_thanh_toan <> 'refunded'
    GROUP BY ct.ten_san_pham
    ORDER BY quantity DESC, revenue DESC
    LIMIT 8
");
$productStmt->execute([':start' => $start, ':end' => $endExclusive]);
$topProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

$orderStmt = $pdo->prepare("
    SELECT ma_don_hang, ten_nguoi_nhan, thanh_tien, phuong_thuc_thanh_toan,
           trang_thai_thanh_toan, trang_thai_don_hang, ngay_dat
    FROM don_hang
    WHERE ngay_dat >= :start AND ngay_dat < :end
    ORDER BY ngay_dat DESC
    LIMIT 15
");
$orderStmt->execute([':start' => $start, ':end' => $endExclusive]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/admin-header.php';
?>
<link rel="stylesheet" href="doanh-thu.css?v=1">

<section class="revenue-page">
  <div class="revenue-heading">
    <div>
      <p class="revenue-eyebrow">LA MUSE · ADMINISTRATION</p>
      <h1>Báo cáo doanh thu</h1>
      <p class="revenue-subtitle">Theo dõi kết quả kinh doanh và những sản phẩm được yêu thích.</p>
    </div>
    <form class="revenue-filter" method="get">
      <label>Từ ngày <input type="date" name="from" value="<?= e($dateFrom) ?>"></label>
      <label>Đến ngày <input type="date" name="to" value="<?= e($dateTo) ?>"></label>
      <button class="btn" type="submit">Lọc báo cáo</button>
    </form>
  </div>

  <div class="revenue-note">
    Doanh thu trong báo cáo này được tính theo đơn đã giao và chưa đánh dấu hoàn tiền.
    Tiền đã thu được thống kê riêng theo trạng thái thanh toán <code>paid</code>.
  </div>

  <div class="revenue-stats">
    <article class="revenue-stat revenue-stat-primary">
      <span>DOANH THU ĐƠN ĐÃ GIAO</span>
      <strong><?= lm_report_money($summary['revenue'] ?? 0) ?></strong>
      <small>Trong khoảng thời gian đã chọn</small>
    </article>
    <article class="revenue-stat">
      <span>ĐƠN ĐÃ GIAO</span>
      <strong><?= number_format((int)($summary['delivered_orders'] ?? 0)) ?></strong>
      <small>Không bao gồm đơn hoàn tiền</small>
    </article>
    <article class="revenue-stat">
      <span>GIÁ TRỊ ĐƠN TRUNG BÌNH</span>
      <strong><?= lm_report_money($summary['average_order'] ?? 0) ?></strong>
      <small>Doanh thu ÷ số đơn đã giao</small>
    </article>
    <article class="revenue-stat">
      <span>TIỀN ĐÃ THU</span>
      <strong><?= lm_report_money($summary['collected'] ?? 0) ?></strong>
      <small>Đơn đã giao, trạng thái thanh toán paid</small>
    </article>
  </div>

  <section class="panel revenue-panel">
    <div class="panel-head">
      <div>
        <h2>Diễn biến doanh thu</h2>
        <div class="muted">Đơn đã giao, chưa đánh dấu hoàn tiền · <?= e(date('d/m/Y', strtotime($dateFrom))) ?> – <?= e(date('d/m/Y', strtotime($dateTo))) ?></div>
      </div>
    </div>
    <div class="revenue-chart-wrap"><canvas id="revenueChart" aria-label="Biểu đồ doanh thu" role="img"></canvas></div>
  </section>

  <div class="revenue-two-columns">
    <section class="panel revenue-panel">
      <div class="panel-head">
        <div><h2>Sản phẩm bán chạy</h2><div class="muted">Xếp theo số lượng trong đơn đã giao.</div></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Doanh thu</th></tr></thead>
          <tbody>
          <?php if (!$topProducts): ?><tr><td colspan="3" class="empty">Chưa có dữ liệu bán hàng trong kỳ.</td></tr><?php endif; ?>
          <?php foreach ($topProducts as $product): ?>
            <tr>
              <td><?= e($product['ten_san_pham']) ?></td>
              <td><?= number_format((int)$product['quantity']) ?></td>
              <td><?= lm_report_money($product['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel revenue-panel">
      <div class="panel-head">
        <div><h2>Đơn đang xử lý</h2><div class="muted">Các đơn chưa giao trong kỳ đã chọn.</div></div>
      </div>
      <div class="revenue-pending-number"><?= number_format($pendingOrders) ?></div>
      <p class="muted">Đơn có trạng thái chờ xác nhận, đã xác nhận hoặc đang giao. Các đơn này không cộng vào doanh thu đơn đã giao.</p>
    </section>
  </div>

  <section class="panel revenue-panel">
    <div class="panel-head">
      <div><h2>Danh sách đơn hàng</h2><div class="muted">Tối đa 15 đơn gần nhất trong khoảng thời gian đã chọn.</div></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Thanh toán</th><th>Trạng thái đơn</th><th class="number-cell">Thành tiền</th></tr></thead>
        <tbody>
        <?php if (!$orders): ?><tr><td colspan="6" class="empty">Không có đơn hàng trong khoảng thời gian này.</td></tr><?php endif; ?>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><strong><?= e($order['ma_don_hang']) ?></strong></td>
            <td><?= e($order['ten_nguoi_nhan']) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($order['ngay_dat']))) ?></td>
            <td><span class="revenue-status"><?= e(match ($order['trang_thai_thanh_toan']) { 'paid' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền', default => 'Chưa thanh toán' }) ?></span></td>
            <td><?= e(match ($order['trang_thai_don_hang']) { 'pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy', default => $order['trang_thai_don_hang'] }) ?></td>
            <td class="number-cell"><?= lm_report_money($order['thanh_tien']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
  const canvas = document.getElementById('revenueChart');
  if (!canvas || typeof Chart === 'undefined') return;
  const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  const values = <?= json_encode($chartValues, JSON_NUMERIC_CHECK) ?>;
  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Doanh thu',
        data: values,
        borderColor: '#68765b',
        backgroundColor: 'rgba(104, 118, 91, .13)',
        fill: true,
        tension: .32,
        borderWidth: 2,
        pointRadius: values.length > 45 ? 0 : 3,
        pointHoverRadius: 5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: context => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(context.parsed.y || 0) } }
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { callback: value => new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(value) + ' ₫' } }
      }
    }
  });
})();
</script>
<?php require __DIR__ . '/admin-footer.php'; ?>
