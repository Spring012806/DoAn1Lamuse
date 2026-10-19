
<?php

session_start();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thanh toán | La Muse</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- CSS của trang thanh toán -->
    <link rel="stylesheet" href="../css/thanhtoan.css">
</head>

<body>

    <main class="checkout-page">

        <!-- Phần tiêu đề trang -->
        <section class="checkout-heading">
            <p class="checkout-eyebrow">LA MUSE — TISSEUSE DE RÊVES
                
            </p>

            <h1>Thanh toán</h1>

            <p class="checkout-description">
                Hoàn tất lựa chọn tinh tế dành riêng cho quý cô.
            </p>

            <!-- Nút quay lại trang giỏ hàng -->
            <a href="../php/giohang.php" class="back-to-cart">
                ← Quay lại giỏ hàng

            </a>
        </section>

        <!-- Chia trang thành thông tin và đơn hàng -->
        <div class="checkout-layout">

            <!-- Cột bên trái -->
            <section class="checkout-form">

                <h2>Thông tin nhận hàng</h2>

                <form action="#" method="post">

                    <div class="form-group">
                        <label for="fullname">Họ và tên</label>
                        <input
                            type="text"
                            id="fullname"
                            name="fullname"
                            placeholder="Nhập họ và tên"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Số điện thoại</label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                placeholder="Nhập số điện thoại"
                                autocomplete="tel"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Nhập email"
                                autocomplete="email"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Địa chỉ nhận hàng</label>
                        <input
                            type="text"
                            id="address"
                            name="address"
                            placeholder="Số nhà, tên đường..."
                            autocomplete="street-address"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="note">Ghi chú đơn hàng</label>
                        <textarea
                            id="note"
                            name="note"
                            rows="3"
                            placeholder="Lời nhắn dành cho La Muse (nếu có)"
                        ></textarea>
                    </div>

                    <!-- Phương thức vận chuyển -->
                    <h2 class="section-heading">Phương thức vận chuyển</h2>

                    <label class="option-card">
                        <input
                            type="radio"
                            name="shipping"
                            value="standard"
                            checked
                        >
                        <span>
                            <strong>Giao hàng tiêu chuẩn</strong>
                            <small>Ngày giao dự kiến từ 3-5 ngày</small>
                        </span>
                    </label>

                    <!-- Phương thức thanh toán -->
                    <!-- Khối 1: Thanh toán khi nhận hàng -->
                    <h2 class="section-heading">Phương thức thanh toán</h2>

                    <label class="option-card">
                        <input
                            type="radio"
                            name="payment"
                            value="cod"
                            checked
                        >
                        <span>
                            <strong>Thanh toán khi nhận hàng (COD)</strong>
                            <small>Thanh toán sau khi nhận sản phẩm</small>
                        </span>
                    </label>

                    <!-- Khối 2: Chuyển khoản ngân hàng -->
                    <label class="option-card">
                        <input
                            type="radio"
                            name="payment"
                            value="bank"
                        >

                    <span class="option-content">
                        <strong>Chuyển khoản qua tài khoản ngân hàng</strong>
                        <small>Thanh toán qua tài khoản ngân hàng</small>
                    </span>
                    </label>

                    <button type="submit" class="checkout-button">
                        XÁC NHẬN ĐẶT HÀNG
                    </button>

                   

                </form>
            </section>

            <!-- Cột bên phải -->
            <aside class="order-summary">

                <h2>Đơn hàng của quý cô</h2>

                <!-- Sản phẩm minh họa -->
                <div class="summary-product">

                    <div class="product-placeholder">
                        LM
                    </div>

                    <div class="product-info">
                        <h3>Tên sản phẩm</h3>
                        <p>Thông tin sản phẩm</p>
                        <span>Giá tiền</span>
                    </div>

                </div>

                <div class="summary-divider"></div>

                <div class="summary-row">
                    <span>Tạm tính</span>
                    <span>đ</span>
                </div>

                <div class="summary-row">
                    <span>Phí vận chuyển</span>
                    <span>30.000₫</span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row total-row">
                    <strong>Tổng thanh toán</strong>
                    <strong>đ</strong>
                </div>

                <p class="summary-note">
                    Cảm ơn nàng đã lựa chọn La Muse.
                </p>

            </aside>

        </div>
    </main>

</body>
</html>