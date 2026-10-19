<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Kiểm tra đã đăng nhập hay chưa.
 */
function lm_is_logged_in(): bool
{
    return isset($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] > 0;
}

/**
 * Kiểm tra tài khoản có quyền admin hay không.
 */
function lm_is_admin(): bool
{
    return lm_is_logged_in()
        && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Chuyển hướng sau khi đăng nhập.
 */
function lm_redirect_after_login(): string
{
    // Admin luôn vào trang quản trị.
    if (lm_is_admin()) {
        return 'admin/index.php';
    }

    $fallback = 'index.php';
    $next = $_SESSION['lm_after_login'] ?? $_GET['next'] ?? $fallback;

    unset($_SESSION['lm_after_login']);

    if (
        !is_string($next)
        || $next === ''
        || str_contains($next, "\r")
        || str_contains($next, "\n")
    ) {
        return $fallback;
    }

    // Chặn chuyển hướng ra ngoài website.
    if (
        preg_match('~^(https?:)?//~i', $next)
        || str_starts_with($next, '/')
        || str_contains($next, ':')
    ) {
        return $fallback;
    }

    // Không cho khách hàng chuyển thẳng vào khu vực admin.
    if (
        preg_match('~(^|/)admin(?:/|$)~i', $next)
    ) {
        return $fallback;
    }

    return $next;
}

/**
 * Bắt buộc đăng nhập.
 */
function lm_require_login(): void
{
    if (!lm_is_logged_in()) {
        header('Location: ../login.php');
        exit;
    }
}

/**
 * Chỉ admin được truy cập.
 */
function lm_require_admin(): void
{
    if (!lm_is_logged_in()) {
        header('Location: ../login.php');
        exit;
    }

    if (!lm_is_admin()) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập trang này.');
    }
}

function lm_h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
