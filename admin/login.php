<?php
require_once __DIR__ . '/bootstrap.php';
if (admin_logged_in()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    try {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, ho_ten, trang_thai FROM admin_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && (int)$user['trang_thai'] === 1 && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['ho_ten'] ?: $user['username'];
            redirect('index.php');
        }
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    } catch (PDOException $ex) {
        $error = 'Chưa có bảng tài khoản quản trị. Hãy chạy SQL khởi tạo trong README.md.';
    }
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đăng nhập · La Muse</title><link rel="stylesheet" href="admin.css"></head><body class="login-page">
<div class="login-card"><div class="login-logo">La Muse<small>ESPACE ADMIN</small></div><h1>Chào mừng trở lại</h1><p class="muted" style="text-align:center">Đăng nhập để quản lý cửa hàng của bạn.</p>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>TÊN ĐĂNG NHẬP</label><input name="username" autocomplete="username" required autofocus></div><div class="field"><label>MẬT KHẨU</label><input type="password" name="password" autocomplete="current-password" required></div><button class="btn" type="submit">Đăng nhập vào hệ thống</button></form>
<div class="hint">Chỉ dành cho quản trị viên được cấp quyền. Không chia sẻ thông tin đăng nhập.</div></div></body></html>
