<?php
declare(strict_types=1);
session_start();

require_once dirname(__DIR__) . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('config.php phải khởi tạo kết nối PDO trong biến $pdo.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

const ADMIN_UPLOAD_DIR = __DIR__ . '/../uploads/products';
const ADMIN_UPLOAD_URL = '../uploads/products';

function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $to): never {
    header('Location: ' . $to);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Phiên biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.');
    }
}

function admin_logged_in(): bool {
    return !empty($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] > 0
        && ($_SESSION['user_role'] ?? '') === 'admin';
}

function require_admin(): void {
    if (!admin_logged_in()) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập trang quản trị La Muse.');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type'=>$type, 'message'=>$message];
}
function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function slugify(string $text): string {
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: 'san-pham';
}
function upload_product_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Tải ảnh lên thất bại.');
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Ảnh tối đa 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    if (!isset($exts[$mime])) throw new RuntimeException('Chỉ nhận ảnh JPG, PNG hoặc WEBP.');
    if (!is_dir(ADMIN_UPLOAD_DIR) && !mkdir(ADMIN_UPLOAD_DIR, 0755, true) && !is_dir(ADMIN_UPLOAD_DIR)) {
        throw new RuntimeException('Không tạo được thư mục uploads/products.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], ADMIN_UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Không lưu được tệp ảnh.');
    }
    return 'uploads/products/' . $name;
}
function delete_local_image(?string $path): void {
    if (!$path || !str_starts_with($path, 'uploads/products/')) return;
    $full = dirname(__DIR__) . '/' . $path;
    if (is_file($full)) @unlink($full);
}
