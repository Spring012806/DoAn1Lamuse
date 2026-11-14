(() => {
    'use strict';

    const config = window.LMNotificationsConfig || {};
    const api = config.api || 'notifications-api.php';
    const csrf = config.csrf || '';

    async function getJSON(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options
        });

        let data;

        try {
            data = await response.json();
        } catch {
            throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Không thể xử lý thông báo.');
        }

        return data;
    }

    function updateBadges(count) {
        const safeCount = Math.max(0, Number(count) || 0);

        document.querySelectorAll(
            '#notification-count, #admin-notification-count'
        ).forEach((badge) => {
            badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
            badge.classList.toggle('is-visible', safeCount > 0);
            badge.setAttribute('aria-label', `${safeCount} thông báo chưa đọc`);
        });
    }

    async function refreshCount() {
        try {
            const data = await getJSON(`${api}?action=count`);
            updateBadges(data.count);
        } catch (error) {
            // Giữ nguyên giao diện nếu API tạm thời không truy cập được.
            console.warn('Không tải được số thông báo:', error.message);
        }
    }

    async function postAction(action, values = {}) {
        const body = new URLSearchParams({
            csrf_token: csrf,
            ...values
        });

        return getJSON(`${api}?action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        });
    }

    function markItemAsRead(id) {
        const item = document.querySelector(
            `.lm-notification-item[data-notification-id="${CSS.escape(String(id))}"]`
        );

        if (!item) return;

        item.classList.remove('is-unread');

        const label = item.querySelector('.lm-notification-unread-label');
        if (label) label.remove();

        const button = item.querySelector(
            `.lm-notification-read[data-read-id="${CSS.escape(String(id))}"]`
        );
        if (button) button.remove();
    }

    document.addEventListener('click', async (event) => {
        const readButton = event.target.closest('.lm-notification-read');

        if (readButton) {
            const id = readButton.dataset.readId;
            if (!id) return;

            readButton.disabled = true;

            try {
                await postAction('read', { id });
                markItemAsRead(id);
                await refreshCount();
            } catch (error) {
                alert(error.message);
                readButton.disabled = false;
            }

            return;
        }

        const readAllButton = event.target.closest('#lm-read-all');

        if (readAllButton) {
            readAllButton.disabled = true;

            try {
                await postAction('read_all');

                document.querySelectorAll(
                    '.lm-notification-item[data-notification-id]'
                ).forEach((item) => {
                    markItemAsRead(item.dataset.notificationId);
                });

                updateBadges(0);
                await refreshCount();
            } catch (error) {
                alert(error.message);
            } finally {
                readAllButton.disabled = false;
            }
        }
    });

    // Tải ngay khi trang mở và cập nhật định kỳ.
    refreshCount();
    window.setInterval(refreshCount, 30000);
})();
