
<?php
require_once __DIR__ . '/bootstrap.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    try {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, password_hash, status, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (
            $user
            && $user['role'] === 'admin'
            && $user['status'] === 'active'
            && password_verify($password, $user['password_hash'])
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            redirect('index.php');
        }

        $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền quản trị.';
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'Không thể đăng nhập lúc này. Vui lòng thử lại.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · La Muse</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            La Muse
            <small>ESPACE ADMIN</small>
        </div>

        <h1>Chào mừng trở lại</h1>

        <p class="muted" style="text-align:center">
            Đăng nhập để quản lý cửa hàng của bạn.
        </p>

        <?php if ($error): ?>
            <div class="alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <div class="field">
                <label for="email">EMAIL QUẢN TRỊ</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">MẬT KHẨU</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button class="btn" type="submit">
                Đăng nhập vào hệ thống
            </button>
        </form>

        <div class="hint">
            Chỉ dành cho tài khoản có quyền quản trị.
            Không chia sẻ thông tin đăng nhập.
        </div>
    </div>
</body>
</html>
