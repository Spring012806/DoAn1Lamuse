<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$pageTitle = 'Quản lý khách hàng';
$active = 'users';

// Tìm kiếm và lọc
$keyword = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');

if (!in_array($status, ['', 'active', 'locked'], true)) {
    $status = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = '(full_name LIKE :name OR email LIKE :email OR phone LIKE :phone)';
    $params['name'] = '%' . $keyword . '%';
    $params['email'] = '%' . $keyword . '%';
    $params['phone'] = '%' . $keyword . '%';
}

if ($status !== '') {
    $where[] = 'status = :status';
    $params['status'] = $status;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Thống kê toàn bộ khách hàng
$totalUsers = (int)$pdo->query(
    'SELECT COUNT(*) FROM users'
)->fetchColumn();

$activeUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'active'"
)->fetchColumn();

$lockedUsers = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE status = 'locked'"
)->fetchColumn();

// Tổng số kết quả sau tìm kiếm/lọc
$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM users' . $whereSql
);
$countStmt->execute($params);
$filteredCount = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Lấy danh sách khách hàng
$sql = 'SELECT id, full_name, email, phone,
               dia_chi, phuong_xa, tinh_thanh,
               status, created_at
        FROM users' . $whereSql . '
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll();

require __DIR__ . '/admin-header.php';
?>

<style>
.users-page { display: grid; gap: 20px; }
.users-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.users-stat, .users-panel {
    background: #fff;
    border: 1px solid #eee8e5;
    border-radius: 14px;
    padding: 20px;
}
.users-stat span { color: #777; font-size: 14px; }
.users-stat strong {
    display: block;
    font-size: 28px;
    margin-top: 8px;
}
.users-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.users-filters input, .users-filters select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    min-width: 160px;
}
.users-btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 9px 12px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
}
.users-btn-primary { background: #342720; color: #fff; }
.users-btn-lock { background: #fff0e8; color: #9a451f; }
.users-btn-unlock { background: #e8f6ed; color: #216b3b; }
.users-table-wrap { overflow-x: auto; }
.users-table { width: 100%; border-collapse: collapse; }
.users-table th, .users-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: 14px;
    white-space: nowrap;
}
.users-table th { color: #777; font-weight: 600; }
.users-status {
    display: inline-block;
    border-radius: 20px;
    padding: 5px 9px;
    font-size: 12px;
}
.users-status.active { background: #e8f6ed; color: #216b3b; }
.users-status.locked { background: #fff0e8; color: #9a451f; }
.users-pagination {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 18px;
    flex-wrap: wrap;
}
.users-pagination a, .users-pagination span {
    padding: 8px 11px;
    border: 1px solid #eee;
    border-radius: 7px;
    text-decoration: none;
    color: inherit;
}
.users-pagination .current { background: #342720; color: white; }
.users-empty { padding: 28px; text-align: center; color: #777; }
@media (max-width: 700px) {
    .users-stats { grid-template-columns: 1fr; }
}
</style>

<div class="users-page">

    <div class="users-stats">
        <div class="users-stat">
            <span>Tổng khách hàng</span>
            <strong><?= number_format($totalUsers) ?></strong>
        </div>
        <div class="users-stat">
            <span>Tài khoản hoạt động</span>
            <strong><?= number_format($activeUsers) ?></strong>
        </div>
        <div class="users-stat">
            <span>Tài khoản bị khóa</span>
            <strong><?= number_format($lockedUsers) ?></strong>
        </div>
    </div>

    <section class="users-panel">
        <form method="get" class="users-filters">
            <input
                type="search"
                name="q"
                placeholder="Tìm tên, email, số điện thoại..."
                value="<?= e($keyword) ?>"
            >

            <select name="status">
                <option value="">Tất cả trạng thái</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                    Đang hoạt động
                </option>
                <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>
                    Đã khóa
                </option>
            </select>

            <button class="users-btn users-btn-primary" type="submit">
                Tìm kiếm
            </button>

            <a class="users-btn" href="users.php">Xóa bộ lọc</a>
        </form>

        <div class="users-table-wrap">
            <table class="users-table">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Khách hàng</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Địa chỉ</th>
                    <th>Phường/Xã</th>
                    <th>Tỉnh/Thành phố</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

                <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="10" class="users-empty">
                            Không tìm thấy khách hàng phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>

<td><?= (int)$user['id'] ?></td>
<td><?= e($user['full_name']) ?></td>
<td><?= e($user['email']) ?></td>
<td><?= e($user['phone'] ?: '—') ?></td>
<td><?= e($user['dia_chi'] ?: '—') ?></td>
<td><?= e($user['phuong_xa'] ?: '—') ?></td>
<td><?= e($user['tinh_thanh'] ?: '—') ?></td>
<td>
    <span class="users-status <?= e($user['status']) ?>">
        <?= $user['status'] === 'active'
            ? 'Hoạt động'
            : 'Đã khóa' ?>
    </span>
</td>
<td>
    <?= e(
        $user['created_at']
            ? date('d/m/Y H:i', strtotime($user['created_at']))
            : '—'
    ) ?>
</td>

                            <td>
                                <form
                                    method="post"
                                    action="user-action.php"
                                    onsubmit="return confirm('Bạn có chắc muốn <?= $user['status'] === 'active' ? 'khóa' : 'mở khóa' ?> tài khoản này?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrf_token()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$user['id'] ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="<?= $user['status'] === 'active' ? 'lock' : 'unlock' ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="users-btn <?= $user['status'] === 'active'
                                            ? 'users-btn-lock'
                                            : 'users-btn-unlock' ?>"
                                    >
                                        <?= $user['status'] === 'active'
                                            ? 'Khóa tài khoản'
                                            : 'Mở khóa' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="users-pagination">
            <span>
                <?= $filteredCount > 0 ? $offset + 1 : 0 ?>
                –<?= min($offset + $perPage, $filteredCount) ?>
                / <?= $filteredCount ?> khách hàng
            </span>

            <?php if ($page > 1): ?>
                <a href="?<?= e(http_build_query([
                    'q' => $keyword,
                    'status' => $status,
                    'page' => $page - 1
                ])) ?>">← Trước</a>
            <?php endif; ?>

            <span class="current">
                <?= $page ?> / <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= e(http_build_query([
                    'q' => $keyword,
                    'status' => $status,
                    'page' => $page + 1
                ])) ?>">Sau →</a>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/admin-footer.php'; ?>
