<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Nếu đã đăng nhập, chuyển đến trang phù hợp.
if (lm_is_logged_in()) {
    header('Location: ' . lm_redirect_after_login());
    exit;
}

$error = '';
$email = '';

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $next = (string) ($_POST['next'] ?? 'index.php');

    // Kiểm tra dữ liệu người dùng nhập.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Vui lòng nhập email hợp lệ và mật khẩu.';
    } else {
        try {
            // Tìm tài khoản theo email trong bảng users.
            $stmt = $pdo->prepare(
                'SELECT id, full_name, email, password_hash, status, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra tài khoản và mật khẩu.
            if (
                !$user ||
                !password_verify($password, (string) $user['password_hash'])
            ) {
                $error = 'Email hoặc mật khẩu chưa chính xác.';
            } elseif (($user['status'] ?? 'active') !== 'active') {
                $error = 'Tài khoản của bạn hiện đang bị khóa. Vui lòng liên hệ La Muse.';
            } else {
                // Đăng nhập thành công.
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = (string) $user['full_name'];
                $_SESSION['user_email'] = (string) $user['email'];
                $_SESSION['user_role'] = (string) ($user['role'] ?? 'user');

                // Phân quyền chuyển hướng.
                if ($_SESSION['user_role'] === 'admin') {
                    unset($_SESSION['lm_after_login']);

                    header('Location: admin/index.php');
                    exit;
                }

                // Tài khoản khách hàng.
                $_SESSION['lm_after_login'] = $next;

                header('Location: ' . lm_redirect_after_login());
                exit;
            }
        } catch (PDOException $e) {
            // Ghi lỗi chi tiết vào log máy chủ, không hiển thị cho khách.
            error_log('Lỗi đăng nhập La Muse: ' . $e->getMessage());

            $error = 'Chưa thể đăng nhập lúc này. Hãy kiểm tra cấu hình và bảng users trong database lamuse.';
        }
    }
}
?>

<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập | La Muse</title>
  <link rel="stylesheet" href="auth.css">
  <script src="auth.js" defer></script>
</head>
<body class="auth-page login-page">
  <main class="auth-shell login-shell">
    <section class="auth-form-panel login-form-panel">
      <a class="brand brand-login" href="index.php" aria-label="La Muse - Trang chủ">LA MUSE<span>Fashion your story</span></a>
      <div class="form-content">
        <p class="eyebrow">WELCOME BACK</p>
        <h1 class="script-heading">Chào mừng trở lại!</h1>
        <p class="form-intro">Đăng nhập để tiếp tục hành trình của bạn.</p>
        <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= lm_h($error) ?></div><?php endif; ?>
        <form method="post" action="login.php" class="auth-form" data-auth-form>
          <input type="hidden" name="next" value="<?= lm_h($next) ?>">
          <label for="email">Email của bạn</label>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="name@example.com" value="<?= lm_h($email) ?>" required>
          <label for="password">Mật khẩu</label>
          <div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu nha gái iu" required><button class="password-toggle" type="button" data-toggle-password="password">Hiện</button></div>
          <div class="form-options"><label class="check-label"><input type="checkbox" name="remember" value="1"> <span>Ghi nhớ đăng nhập</span></label><a href="mailto: support@lamuse.local" class="text-link" title="Liên hệ để được hỗ trợ đặt lại mật khẩu">Quên mật khẩu?</a></div>
          <button class="submit-button" type="submit">ĐĂNG NHẬP</button>
        </form>
        <div class="or-divider"><span>HOẶC</span></div>
        <p class="switch-auth">Bạn chưa có tài khoản hả? <a href="register.php">Đăng ký ở đây nè</a></p>
      </div>
      <p class="copyright">© <?= date('Y') ?> LA MUSE. FASHION FOR YOUR STORY.</p>
    </section>
    <aside class="auth-story login-story">
      <p class="story-kicker">BÍ MẬT CỦA VẺ ĐẸP NẰM Ở SỰ TỰ TIN</p>
      <h2>Mỗi ngày là<br>một khởi đầu<br><em>mới.</em></h2>
      <span class="story-rule"></span>
      <p>Dành một chút thời gian cho bản thân, và bắt đầu<br class="desktop-only"> từ những điều giản dị nhất.</p>
    </aside>
  </main>
</body>
</html>
