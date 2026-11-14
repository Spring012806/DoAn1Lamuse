<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once dirname(__DIR__) . '/notification-helper.php';

$pageTitle = 'Thông báo';
$active = 'notifications';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$marketingMessage = '';
$marketingError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_marketing') {
    $sentToken = (string) ($_POST['csrf_token'] ?? '');
    if ($sentToken === '' || !hash_equals($csrfToken, $sentToken)) {
        $marketingError = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
    } else {
        $marketingType = (string) ($_POST['marketing_type'] ?? '');
        $allowedMarketingTypes = [
            'marketing_promotion' => 'Thông báo khuyến mãi',
            'marketing_new_product' => 'Thông báo sản phẩm mới',
            'marketing_discount' => 'Thông báo chương trình giảm giá',
        ];
        $title = trim((string) ($_POST['marketing_title'] ?? ''));
        $body = trim((string) ($_POST['marketing_body'] ?? ''));
        $link = trim((string) ($_POST['marketing_link'] ?? ''));

        if (!isset($allowedMarketingTypes[$marketingType])) {
            $marketingError = 'Vui lòng chọn đúng loại thông báo.';
        } elseif ($title === '' || mb_strlen($title) > 200 || $body === '') {
            $marketingError = 'Vui lòng nhập tiêu đề (tối đa 200 ký tự) và nội dung thông báo.';
        } elseif ($link !== '' && (str_starts_with($link, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $link))) {
            $marketingError = 'Liên kết chỉ được dùng đường dẫn nội bộ của website.';
        } else {
            $recipientCount = lm_notify_all_customers(
                $pdo,
                $title,
                $body,
                $marketingType,
                $link !== '' ? $link : null
            );
            $marketingMessage = 'Đã gửi thông báo đến ' . $recipientCount . ' khách hàng.';
        }
    }
}

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $marketingMessage = 'Đã gửi thông báo tiếp thị thành công.';
}

$stmt = $pdo->prepare(
    'SELECT id, tieu_de, noi_dung, loai, lien_ket, da_doc, created_at
     FROM thong_bao
     WHERE nguoi_nhan_id = :uid
       AND vai_tro_nhan = :role
     ORDER BY created_at DESC, id DESC
     LIMIT 100'
);
$stmt->execute([
    'uid' => (int) $_SESSION['user_id'],
    'role' => 'admin',
]);
$notifications = $stmt->fetchAll();

function lm_admin_notification_h(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

require __DIR__ . '/admin-header.php';
?>

<link rel="stylesheet" href="../assets/css/notifications.css">

<section class="lm-admin-notifications">
    <div class="lm-admin-notifications-heading">
        <p class="lm-notifications-eyebrow">LA MUSE · ADMINISTRATION</p>
        <h1>Thông báo quản trị</h1>
        <p>Cập nhật đơn hàng và các hoạt động cần bạn theo dõi.</p>
    </div>

    <section class="lm-marketing-composer" style="margin: 24px 0; padding: 22px; border: 1px solid #d8cfb6; border-radius: 14px; background: #fffdf7;">
        <h2 style="margin: 0 0 8px; color: #31583b;">Gửi thông báo tiếp thị</h2>
        <p style="margin: 0 0 16px; color: #6d705f;">Thông báo sẽ được gửi đến tất cả tài khoản khách hàng đang hoạt động.</p>
        <?php if ($marketingMessage !== ''): ?><p role="status" style="padding: 10px 12px; background: #edf4e9; border-radius: 8px;"><?= lm_admin_notification_h($marketingMessage) ?></p><?php endif; ?>
        <?php if ($marketingError !== ''): ?><p role="alert" style="padding: 10px 12px; background: #fff0ed; border-radius: 8px;"><?= lm_admin_notification_h($marketingError) ?></p><?php endif; ?>
        <form method="post" style="display: grid; gap: 12px;">
            <input type="hidden" name="csrf_token" value="<?= lm_admin_notification_h($csrfToken) ?>">
            <input type="hidden" name="action" value="send_marketing">
            <label>Loại thông báo
                <select name="marketing_type" required style="display:block;width:100%;margin-top:6px;padding:10px;border:1px solid #d8cfb6;border-radius:8px;">
                    <option value="marketing_promotion">Thông báo khuyến mãi</option>
                    <option value="marketing_new_product">Thông báo sản phẩm mới</option>
                    <option value="marketing_discount">Thông báo chương trình giảm giá</option>
                </select>
            </label>
            <label>Tiêu đề
                <input name="marketing_title" maxlength="200" required placeholder="Ví dụ: Ưu đãi tháng 11 tại La Muse" style="display:block;width:100%;margin-top:6px;padding:10px;border:1px solid #d8cfb6;border-radius:8px;">
            </label>
            <label>Nội dung
                <textarea name="marketing_body" rows="3" required placeholder="Nhập nội dung gửi đến khách hàng..." style="display:block;width:100%;margin-top:6px;padding:10px;border:1px solid #d8cfb6;border-radius:8px;"></textarea>
            </label>
            <label>Liên kết nội bộ (không bắt buộc)
                <input name="marketing_link" maxlength="500" placeholder="products.php" style="display:block;width:100%;margin-top:6px;padding:10px;border:1px solid #d8cfb6;border-radius:8px;">
            </label>
            <div><button type="submit" style="padding:11px 18px;border:0;border-radius:8px;background:#31583b;color:#fff;cursor:pointer;" onclick="return confirm('Gửi thông báo này đến toàn bộ khách hàng đang hoạt động?')">Gửi đến khách hàng</button></div>
        </form>
    </section>

    <div class="lm-notifications-toolbar">
        <span>Hộp thư quản trị</span>
        <button type="button" id="lm-read-all">
            Đánh dấu tất cả đã đọc
        </button>
    </div>

    <div id="lm-notifications-list">
        <?php if (!$notifications): ?>
            <div class="lm-notifications-empty">
                <span class="lm-notifications-empty-icon">✧</span>
                <h2>Chưa có thông báo</h2>
                <p>Khi có hoạt động mới, thông báo sẽ xuất hiện tại đây.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notice): ?>
                <?php
                $link = trim((string) ($notice['lien_ket'] ?? ''));

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
                            <h2><?= lm_admin_notification_h($notice['tieu_de']) ?></h2>
                            <?php if ((int) $notice['da_doc'] === 0): ?>
                                <span class="lm-notification-unread-label">Mới</span>
                            <?php endif; ?>
                        </div>

                        <p><?= nl2br(lm_admin_notification_h($notice['noi_dung'])) ?></p>

                        <time>
                            <?= lm_admin_notification_h(date('d/m/Y H:i', strtotime($notice['created_at']))) ?>
                        </time>

                        <div class="lm-notification-actions">
                            <?php if ($link !== ''): ?>
                                <a class="lm-notification-open"
                                   href="<?= lm_admin_notification_h($link) ?>">
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

<script>
window.LMNotificationsConfig = {
    api: '../notifications-api.php',
    csrf: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
};
</script>
<script src="../assets/js/notifications.js" defer></script>

<?php require __DIR__ . '/admin-footer.php'; ?>
