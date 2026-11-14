<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);

if ($stmt->fetchColumn() !== 'customer') {
    http_response_code(403);
    exit('Trang này dành cho khách hàng.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$stmt = $pdo->prepare(
    'SELECT id, tieu_de, noi_dung, loai, lien_ket, da_doc, created_at
     FROM thong_bao
     WHERE nguoi_nhan_id = :uid
       AND vai_tro_nhan = :role
     ORDER BY created_at DESC, id DESC
     LIMIT 100'
);
$stmt->execute([
    'uid' => $userId,
    'role' => 'customer',
]);

$notifications = $stmt->fetchAll();

$lmPageTitle = 'Thông báo — La Muse';
require __DIR__ . '/header.php';

function lm_notification_h(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>

<link rel="stylesheet" href="assets/css/notifications.css">

<main class="lm-notifications-page">
    <section class="lm-notifications-wrap">
        <div class="lm-notifications-heading">
            <p class="lm-notifications-eyebrow">LA MUSE · YOUR ACCOUNT</p>
            <h1>Thông báo của bạn</h1>
            <p>Những tin nhắn nhỏ giúp bạn cập nhật hành trình đơn hàng.</p>
        </div>

        <div class="lm-notifications-toolbar">
            <span>Hộp thư thông báo</span>
            <button type="button" id="lm-read-all">
                Đánh dấu tất cả đã đọc
            </button>
        </div>

        <div id="lm-notifications-list">
            <?php if (!$notifications): ?>
                <div class="lm-notifications-empty">
                    <span class="lm-notifications-empty-icon">✧</span>
                    <h2>Hộp thư vẫn còn trống</h2>
                    <p>Khi có cập nhật mới, thông báo sẽ xuất hiện tại đây.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notice): ?>
                    <?php
                    $link = trim((string) ($notice['lien_ket'] ?? ''));
                    // Chỉ cho phép đường dẫn nội bộ tương đối.
                    if (
                        $link !== ''
                        && (
                            str_starts_with($link, '//')
                            || preg_match('~^[a-z][a-z0-9+.-]*:~i', $link)
                        )
                    ) {
                        $link = '';
                    }
                    ?>
                    <article
                        class="lm-notification-item <?= (int) $notice['da_doc'] === 0 ? 'is-unread' : '' ?>"
                        data-notification-id="<?= (int) $notice['id'] ?>"
                    >
                        <span class="lm-notification-mark" aria-hidden="true">✧</span>

                        <div class="lm-notification-content">
                            <div class="lm-notification-title-row">
                                <h2><?= lm_notification_h($notice['tieu_de']) ?></h2>
                                <?php if ((int) $notice['da_doc'] === 0): ?>
                                    <span class="lm-notification-unread-label">Mới</span>
                                <?php endif; ?>
                            </div>

                            <p><?= nl2br(lm_notification_h($notice['noi_dung'])) ?></p>

                            <time>
                                <?= lm_notification_h(date('d/m/Y H:i', strtotime($notice['created_at']))) ?>
                            </time>

                            <div class="lm-notification-actions">
                                <?php if ($link !== ''): ?>
                                    <a class="lm-notification-open"
                                       href="<?= lm_notification_h($link) ?>">
                                        Xem chi tiết
                                    </a>
                                <?php endif; ?>

                                <?php if ((int) $notice['da_doc'] === 0): ?>
                                    <button type="button"
                                            class="lm-notification-read"
                                            data-read-id="<?= (int) $notice['id'] ?>">
                                        Đánh dấu đã đọc
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
window.LMNotificationsConfig = {
    api: 'notifications-api.php',
    csrf: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>
<script src="assets/js/notifications.js" defer></script>

<?php require __DIR__ . '/footer.php'; ?>
