<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}

verify_csrf();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$userId || !in_array($action, ['lock', 'unlock'], true)) {
    flash('error', 'Yêu cầu không hợp lệ.');
    redirect('users.php');
}

$newStatus = $action === 'lock' ? 'locked' : 'active';

try {
    $stmt = $pdo->prepare(
        'UPDATE users SET status = :status WHERE id = :id'
    );
    $stmt->execute([
        'status' => $newStatus,
        'id' => $userId,
    ]);

    if ($stmt->rowCount() > 0) {
        flash(
            'success',
            $action === 'lock'
                ? 'Đã khóa tài khoản khách hàng.'
                : 'Đã mở khóa tài khoản khách hàng.'
        );
    } else {
        // Kiểm tra tài khoản có tồn tại hay trạng thái đã giống yêu cầu.
        $check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$userId]);

        if ($check->fetch()) {
            flash('success', 'Trạng thái tài khoản đã được cập nhật.');
        } else {
            flash('error', 'Không tìm thấy tài khoản khách hàng.');
        }
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('error', 'Không thể cập nhật tài khoản. Vui lòng thử lại.');
}

redirect('users.php');
