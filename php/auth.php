<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function lm_is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function lm_redirect_after_login(): string
{
    $fallback = 'index.php';
    $next = $_SESSION['lm_after_login'] ?? $_GET['next'] ?? $fallback;
    unset($_SESSION['lm_after_login']);

    if (!is_string($next) || $next === '' || str_contains($next, "\r") || str_contains($next, "\n")) {
        return $fallback;
    }

    // Chỉ cho phép đường dẫn tương đối trong website, ngăn open redirect.
    if (preg_match('~^(https?:)?//~i', $next) || str_starts_with($next, '/') || str_contains($next, ':')) {
        return $fallback;
    }

    return $next;
}

function lm_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
