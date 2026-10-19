La Muse - cấu trúc PHP đã tách header/footer

Tệp:
- header.php: phần <!DOCTYPE html>, <head>, thông báo và thanh điều hướng.
- index.php: nội dung chính của trang chủ, include header.php và footer.php.
- footer.php: chân trang, toast, script.js và thẻ đóng body/html.
- style.css, script.js: giữ nguyên từ bản bạn gửi.

Cách chạy:
1. Đặt toàn bộ thư mục lamuse_split vào thư mục dự án của WampServer (ví dụ www/LaMuse).
2. Đảm bảo thư mục images/ của bạn nằm cùng cấp với index.php.
3. Mở http://localhost/LaMuse/index.php

Khi tạo trang PHP khác, đặt include 'header.php'; ở đầu trang và include 'footer.php'; ở cuối trang.
Lưu ý: các liên kết trong header hiện giữ nguyên dạng anchor (#products, #collections...) như bản trang chủ ban đầu; khi dùng ở trang con, có thể đổi thành index.php#products, index.php#collections... để quay về đúng mục trên trang chủ.
