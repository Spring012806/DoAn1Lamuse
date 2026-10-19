<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// if (lm_is_logged_in()) {
//     header('Location: index.php');
//     exit;
// }

$error = '';
$fullName = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $terms = isset($_POST['terms']);

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $error = 'Vui lòng nhập họ tên (không quá 100 ký tự).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu cần có ít nhất 8 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận chưa khớp.';
    } elseif (!$terms) {
        $error = 'Bạn cần đồng ý với điều khoản sử dụng để tiếp tục.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                $error = 'Email này đã được đăng ký. Bạn hãy đăng nhập nhé.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, status) VALUES (:full_name, :email, :password_hash, 'active')");
                $insert->execute(['full_name' => $fullName, 'email' => $email, 'password_hash' => $hash]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Chưa thể tạo tài khoản. Hãy kiểm tra bảng users trong database lamuse.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng ký | La Muse</title>
  <link rel="stylesheet" href="../css/auth.css">
  <script src="../js/auth.js" defer></script>
</head>
<body class="auth-page register-page">
  <main class="auth-shell register-shell">
    <aside class="auth-story register-story">
      <div class="register-story-copy"><p class="story-kicker">LA MUSE</p><h2>Elegance in<br>Every Moment</h2><p>Khám phá vẻ đẹp riêng của bạn.</p></div>
    </aside>
    <section class="auth-form-panel register-form-panel">
      <a class="brand brand-register" href="index.php">La Muse</a>
      <div class="register-heading"><h1 class="script-heading">Tạo tài khoản</h1><p>Khám phá phong cách của riêng bạn cùng La Muse.</p></div>
      <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= lm_h($error) ?></div><?php endif; ?>
      <form method="post" action="register.php" class="auth-form register-form" data-auth-form>
        <label for="full_name">Họ và tên</label><input id="full_name" name="full_name" type="text" autocomplete="name" placeholder="Nhập họ và tên" maxlength="100" value="<?= lm_h($fullName) ?>" required>
        <label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" placeholder="example@email.com" value="<?= lm_h($email) ?>" required>
        <label for="password">Mật khẩu</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Ít nhất 8 ký tự" required><button class="password-toggle" type="button" data-toggle-password="password">Hiện</button></div>
        <label for="confirm_password">Xác nhận mật khẩu</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu" required><button class="password-toggle" type="button" data-toggle-password="confirm_password">Hiện</button></div>
        <label class="terms-label"><input type="checkbox" name="terms" value="1" required><span>Tôi đồng ý với điều khoản sử dụng.</span></label>
        <button class="submit-button" type="submit">ĐĂNG KÝ</button>
      </form>
      <p class="switch-auth register-switch">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </section>
  </main>
</body>
</html>
