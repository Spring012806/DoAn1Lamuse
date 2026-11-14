<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function lm_api_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    lm_api_json(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.'], 500);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId < 1) {
    lm_api_json(['success' => false, 'message' => 'Bạn cần đăng nhập.'], 401);
}

// Lấy vai trò thực tế từ database, không tin role gửi từ trình duyệt.
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$userRole = $stmt->fetchColumn();

if (!in_array($userRole, ['customer', 'admin'], true)) {
    lm_api_json(['success' => false, 'message' => 'Tài khoản không hợp lệ.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET') {
    if ($action === 'count') {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM thong_bao
             WHERE nguoi_nhan_id = :uid
               AND vai_tro_nhan = :role
               AND da_doc = 0'
        );
        $stmt->execute([
            'uid' => $userId,
            'role' => $userRole,
        ]);

        lm_api_json([
            'success' => true,
            'count' => (int) $stmt->fetchColumn(),
        ]);
    }

    if ($action === 'list') {
        $stmt = $pdo->prepare(
            'SELECT id, tieu_de, noi_dung, loai, lien_ket,
                    da_doc, created_at
             FROM thong_bao
             WHERE nguoi_nhan_id = :uid
               AND vai_tro_nhan = :role
             ORDER BY created_at DESC, id DESC
             LIMIT 100'
        );
        $stmt->execute([
            'uid' => $userId,
            'role' => $userRole,
        ]);

        lm_api_json([
            'success' => true,
            'notifications' => $stmt->fetchAll(),
        ]);
    }

    lm_api_json(['success' => false, 'message' => 'Thao tác không hợp lệ.'], 400);
}

if ($method === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    if (
        $csrfToken === ''
        || $sessionToken === ''
        || !hash_equals($sessionToken, $csrfToken)
    ) {
        lm_api_json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Hãy tải lại trang.'], 419);
    }

    if ($action === 'read') {
        $notificationId = (int) ($_POST['id'] ?? 0);

        if ($notificationId < 1) {
            lm_api_json(['success' => false, 'message' => 'Thông báo không hợp lệ.'], 400);
        }

        $stmt = $pdo->prepare(
            'UPDATE thong_bao
             SET da_doc = 1
             WHERE id = :id
               AND nguoi_nhan_id = :uid
               AND vai_tro_nhan = :role'
        );
        $stmt->execute([
            'id' => $notificationId,
            'uid' => $userId,
            'role' => $userRole,
        ]);

        lm_api_json(['success' => true]);
    }

    if ($action === 'read_all') {
        $stmt = $pdo->prepare(
            'UPDATE thong_bao
             SET da_doc = 1
             WHERE nguoi_nhan_id = :uid
               AND vai_tro_nhan = :role
               AND da_doc = 0'
        );
        $stmt->execute([
            'uid' => $userId,
            'role' => $userRole,
        ]);

        lm_api_json(['success' => true]);
    }

    lm_api_json(['success' => false, 'message' => 'Thao tác không hợp lệ.'], 400);
}

header('Allow: GET, POST');
lm_api_json(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], 405);
