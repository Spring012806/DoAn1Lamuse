<?php
declare(strict_types=1);

/**
 * Tạo một thông báo cho khách hàng hoặc admin.
 */
function lm_create_notification(
    PDO $pdo,
    int $recipientId,
    string $recipientRole,
    string $title,
    string $message,
    string $type = 'general',
    ?string $link = null
): void {
    if (
        $recipientId < 1
        || !in_array($recipientRole, ['customer', 'admin'], true)
    ) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO thong_bao (
            nguoi_nhan_id,
            vai_tro_nhan,
            tieu_de,
            noi_dung,
            loai,
            lien_ket
        ) VALUES (
            :recipient_id,
            :recipient_role,
            :title,
            :message,
            :type,
            :link
        )
    ");

    $stmt->execute([
        'recipient_id' => $recipientId,
        'recipient_role' => $recipientRole,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link,
    ]);
}

/**
 * Gửi thông báo đến tất cả tài khoản admin.
 */
function lm_notify_all_admins(
    PDO $pdo,
    string $title,
    string $message,
    string $type = 'general',
    ?string $link = null
): void {
    $stmt = $pdo->query("
        SELECT id
        FROM users
        WHERE role = 'admin'
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        lm_create_notification(
            $pdo,
            (int) $adminId,
            'admin',
            $title,
            $message,
            $type,
            $link
        );
    }
}


/**
 * Gửi một thông báo tiếp thị đến tất cả tài khoản khách hàng đang hoạt động.
 * Trả về số khách hàng đã nhận thông báo.
 */
function lm_notify_all_customers(
    PDO $pdo,
    string $title,
    string $message,
    string $type,
    ?string $link = null
): int {
    $stmt = $pdo->query("
        SELECT id
        FROM users
        WHERE role = 'customer' AND status = 'active'
    ");

    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $customerId) {
        lm_create_notification(
            $pdo,
            (int) $customerId,
            'customer',
            $title,
            $message,
            $type,
            $link
        );
        $count++;
    }

    return $count;
}
