
<?php
declare(strict_types=1);

// Bắt đầu session nếu chưa có.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Kiểm tra tài khoản đã đăng nhập chưa.
 */
function lm_is_logged_in(): bool
{
    return isset($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] > 0;
}

/**
 * Kiểm tra người dùng có đúng vai trò không.
 */
function lm_has_role(string $role): bool
{
    return lm_is_logged_in()
        && isset($_SESSION['user_role'])
        && $_SESSION['user_role'] === $role;
}

/**
 * Bắt buộc đăng nhập mới được xem trang.
 */
function lm_require_login(): void
{
    if (!lm_is_logged_in()) {
        header('Location: /LaMuse/php/login.php');
        exit;
    }
}

/**
 * Bắt buộc phải là Admin mới được xem trang.
 */
function lm_require_admin(): void
{
    lm_require_login();

    if (!lm_has_role('admin')) {
        http_response_code(403);
        exit('403 - Bạn không có quyền truy cập trang quản trị.');
    }
}

/**
 * Làm sạch dữ liệu trước khi hiển thị lên HTML.
 */
function lm_h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
