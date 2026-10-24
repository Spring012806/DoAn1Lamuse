<?php
/*
|--------------------------------------------------------------------------
| PHẦN 1. KHỞI TẠO VÀ CHUẨN BỊ DỮ LIỆU
|--------------------------------------------------------------------------
*/
declare(strict_types=1);

/*
 * session_start() dùng để mở phiên làm việc của khách hàng.
 *
 * Giỏ hàng ở cart.php đang lưu trong:
 * $_SESSION['lm_cart']
 *
 * Vì vậy, thanhtoan.php phải dùng cùng session thì mới
 * đọc được các sản phẩm khách đã thêm vào giỏ.
 *
 * Kiểm tra session trước để tránh khởi động session hai lần.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| PHẦN 2. HÀM BẢO VỆ DỮ LIỆU HTML
|--------------------------------------------------------------------------
*/

/*
 * Hàm lm_checkout_h() dùng để hiển thị dữ liệu an toàn.
 *
 * Ví dụ:
 * Tên sản phẩm được lấy từ database hoặc session.
 * Nếu hiển thị trực tiếp mà không xử lý, dữ liệu chứa HTML
 * có thể gây lỗi giao diện hoặc tạo nguy cơ XSS.
 *
 * htmlspecialchars() chuyển các ký tự đặc biệt thành dạng
 * an toàn trước khi đưa dữ liệu vào HTML.
 *
 * Hàm này KHÔNG truy vấn database và KHÔNG thay đổi dữ liệu gốc.
 */
function lm_checkout_h($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| PHẦN 3. HÀM ĐỊNH DẠNG TIỀN
|--------------------------------------------------------------------------
*/

/*
 * Hàm lm_checkout_money() chuyển số tiền thành định dạng Việt Nam.
 *
 * Ví dụ:
 * 650000  -> 650.000₫
 * 1250000 -> 1.250.000₫
 *
 * Hàm này chỉ định dạng để hiển thị, không tính lại giá sản phẩm.
 */
function lm_checkout_money($value): string
{
    return number_format((float)$value, 0, ',', '.') . '₫';
}


/*
|--------------------------------------------------------------------------
| PHẦN 4. LẤY DỮ LIỆU GIỎ HÀNG TỪ SESSION
|--------------------------------------------------------------------------
*/

/*
 * Đọc giỏ hàng do cart.php lưu vào session.
 *
 * Mỗi dòng sản phẩm có thể chứa:
 * - variant_id: ID biến thể sản phẩm
 * - product_id: ID sản phẩm
 * - name: tên sản phẩm
 * - image: đường dẫn ảnh
 * - sku: mã SKU
 * - color_name: màu sắc
 * - size_name: kích thước
 * - price: đơn giá
 * - quantity: số lượng
 *
 * Nếu session chưa có giỏ hàng thì dùng mảng rỗng.
 * Kiểm tra kiểu dữ liệu để tránh lỗi khi xử lý foreach.
 *
 * Lưu ý: dữ liệu session ở đây được dùng để HIỂN THỊ.
 * Khi đặt hàng thật, máy chủ vẫn cần kiểm tra lại giá và tồn kho.
 */
$cart = $_SESSION['lm_cart'] ?? [];

if (!is_array($cart)) {
    $cart = [];
}


/*
|--------------------------------------------------------------------------
| PHẦN 5. TÍNH TẠM TÍNH VÀ TỔNG SỐ LƯỢNG
|--------------------------------------------------------------------------
*/

/*
 * $subtotal:
 * Tổng tiền của tất cả sản phẩm, chưa bao gồm vận chuyển.
 *
 * $totalQuantity:
 * Tổng số lượng sản phẩm khách mua.
 *
 * Ví dụ:
 * Váy A: 650.000₫ x 2 = 1.300.000₫
 * Váy B: 850.000₫ x 1 =   850.000₫
 *
 * Tạm tính = 2.150.000₫
 * Tổng số lượng = 3 sản phẩm.
 */
$subtotal = 0;
$totalQuantity = 0;


/*
 * Duyệt từng dòng trong giỏ hàng để tính tiền.
 */
foreach ($cart as $row) {

    // Bỏ qua dòng không có cấu trúc dữ liệu hợp lệ.
    if (!is_array($row)) {
        continue;
    }

    /*
     * Đọc đơn giá và số lượng từ session.
     *
     * ?? 0 có nghĩa là nếu thiếu trường dữ liệu
     * thì sử dụng giá trị mặc định bằng 0.
     *
     * max() ngăn giá trị âm làm sai phép tính.
     */
    $price = max(0, (float)($row['price'] ?? 0));

    $quantity = max(0, (int)($row['quantity'] ?? 0));


    /*
     * Thành tiền của một dòng = đơn giá x số lượng.
     * Cộng dồn vào tổng tiền sản phẩm.
     */
    $subtotal += $price * $quantity;


    /*
     * Cộng số lượng của dòng hiện tại vào tổng số lượng.
     */
    $totalQuantity += $quantity;
}


/*
|--------------------------------------------------------------------------
| PHẦN 6. TÍNH PHÍ VẬN CHUYỂN VÀ TỔNG THANH TOÁN
|--------------------------------------------------------------------------
*/

/*
 * Quy tắc hiện tại:
 * - Giỏ có sản phẩm: phí vận chuyển 30.000₫.
 * - Giỏ không có sản phẩm: phí vận chuyển bằng 0₫.
 *
 * Đây là mức phí cố định minh họa theo giao diện hiện tại.
 * Nếu La Muse có chính sách miễn phí vận chuyển hoặc
 * phí theo khu vực, cần thay đổi quy tắc tại đây.
 */
$shippingFee = $totalQuantity > 0 ? 30000 : 0;


/*
 * Tổng thanh toán = tạm tính + phí vận chuyển.
 */
$grandTotal = $subtotal + $shippingFee;

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <!-- Giúp giao diện hiển thị phù hợp với điện thoại và máy tính -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thanh toán | La Muse</title>

    <!-- Kết nối Google Fonts để giữ font chữ của La Muse -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet"
    >

    <!-- Nạp CSS riêng của trang thanh toán -->
    <link rel="stylesheet" href="thanhtoan.css">
</head>

<body>

<main class="checkout-page">

    <!--
    ----------------------------------------------------------------------
    PHẦN 7. TIÊU ĐỀ TRANG THANH TOÁN
    ----------------------------------------------------------------------

    Đây là phần tiêu đề ở phía trên cùng của trang.
    Các nội dung trong phần này đang được viết cố định trong HTML.
    Không lấy dữ liệu từ database.
    -->

    <section class="checkout-heading">

        <p class="checkout-eyebrow">
            LA MUSE — TISSEUSE DE RÊVES
        </p>

        <h1>Thanh toán</h1>

        <p class="checkout-description">
            Hoàn tất lựa chọn tinh tế dành riêng cho quý cô.
        </p>

        <!-- Nhấn vào để quay lại giỏ hàng và sửa số lượng -->
        <a href="cart.php" class="back-to-cart">
            ← Quay lại giỏ hàng
        </a>

    </section>


    <!-- Chia trang thành cột thông tin nhận hàng và cột đơn hàng -->
    <div class="checkout-layout">


        <!--
        ------------------------------------------------------------------
        PHẦN 8. FORM THÔNG TIN NHẬN HÀNG
        ------------------------------------------------------------------

        Khách nhập thông tin giao hàng tại đây.

        Lưu ý:
        Form hiện sử dụng action="#", nghĩa là chưa kết nối đến
        một đoạn PHP xử lý và lưu đơn hàng hoàn chỉnh.

        required giúp trình duyệt kiểm tra một số trường bắt buộc,
        nhưng không thay thế việc kiểm tra dữ liệu ở máy chủ.
        -->

        <section class="checkout-form">

            <h2>Thông tin nhận hàng</h2>

            <form action="#" method="post">

                <!-- Họ tên người nhận -->
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


                <!-- Số điện thoại và email -->
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


                <!-- Địa chỉ giao hàng -->
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


                <!-- Ghi chú thêm của khách hàng -->
                <div class="form-group">

                    <label for="note">Ghi chú đơn hàng</label>

                    <textarea
                        id="note"
                        name="note"
                        rows="3"
                        placeholder="Lời nhắn dành cho La Muse (nếu có)"
                    ></textarea>

                </div>


                <!--
                Phần lựa chọn vận chuyển.
                Hiện chỉ có một lựa chọn giao hàng tiêu chuẩn.
                Chưa có xử lý thay đổi phí vận chuyển.
                -->

                <h2 class="section-heading">
                    Phương thức vận chuyển
                </h2>

                <label class="option-card">

                    <input
                        type="radio"
                        name="shipping"
                        value="standard"
                        checked
                    >

                    <span>
                        <strong>Giao hàng tiêu chuẩn</strong>

                        <small>
                            Ngày giao dự kiến từ 3-5 ngày
                        </small>
                    </span>

                </label>


                <!--
                Phần lựa chọn phương thức thanh toán.

                COD: thanh toán khi nhận hàng.
                Bank: chuyển khoản ngân hàng.

                Các radio này hiện chỉ ghi nhận lựa chọn trên form.
                Chưa thực hiện thu tiền hoặc xác nhận chuyển khoản.
                -->

                <h2 class="section-heading">
                    Phương thức thanh toán
                </h2>

                <label class="option-card">

                    <input
                        type="radio"
                        name="payment"
                        value="cod"
                        checked
                    >

                    <span>
                        <strong>
                            Thanh toán khi nhận hàng (COD)
                        </strong>

                        <small>
                            Thanh toán sau khi nhận sản phẩm
                        </small>
                    </span>

                </label>


                <label class="option-card">

                    <input
                        type="radio"
                        name="payment"
                        value="bank"
                    >

                    <span class="option-content">

                        <strong>
                            Chuyển khoản qua tài khoản ngân hàng
                        </strong>

                        <small>
                            Thanh toán qua tài khoản ngân hàng
                        </small>

                    </span>

                </label>


                <!--
                Nút gửi thông tin đặt hàng.

                Nếu giỏ hàng rỗng thì nút bị vô hiệu hóa.
                Nếu giỏ hàng có sản phẩm thì nút có thể được nhấn.

                Tuy nhiên, form chưa có xử lý lưu đơn hàng.
                -->

                <button
                    type="submit"
                    class="checkout-button"
                    <?= $totalQuantity === 0 ? 'disabled' : '' ?>
                >
                    XÁC NHẬN ĐẶT HÀNG
                </button>

            </form>

        </section>



        <!--
        ------------------------------------------------------------------
        PHẦN 9. TÓM TẮT ĐƠN HÀNG
        ------------------------------------------------------------------

        Đây chính là phần được khoanh đỏ trong ảnh bạn gửi.

        Khác với code cũ chỉ có "Tên sản phẩm", "Giá tiền",
        phần này sử dụng PHP để lấy dữ liệu trong session.

        Khi giỏ hàng thay đổi và khách mở lại trang thanh toán,
        PHP sẽ đọc dữ liệu session hiện tại để hiển thị.
        -->

        <aside class="order-summary">

            <!-- Tiêu đề của khung đơn hàng -->
            <h2>Đơn hàng của quý cô</h2>


            <!--
            PHẦN 9.1. KIỂM TRA GIỎ HÀNG CÓ SẢN PHẨM KHÔNG

            Nếu tổng số lượng bằng 0:
            - Hiển thị thông báo giỏ hàng trống.
            - Không hiển thị bảng tính tiền.
            - Nút xác nhận đặt hàng cũng bị vô hiệu hóa.
            -->

            <?php if ($totalQuantity === 0): ?>

                <div class="summary-empty">

                    <p>
                        Giỏ hàng của nàng vẫn chưa có sản phẩm.
                    </p>

                    <a href="cart.php" class="back-to-cart">
                        Quay lại giỏ hàng
                    </a>

                </div>


            <?php else: ?>


                <!--
                PHẦN 9.2. HIỂN THỊ TỪNG SẢN PHẨM

                foreach duyệt qua từng dòng trong giỏ hàng.

                Nếu khách mua 3 sản phẩm khác nhau, vòng lặp sẽ
                tạo ra 3 khối thông tin sản phẩm.

                $key là khóa của dòng giỏ hàng.
                $row là mảng thông tin của sản phẩm hiện tại.
                -->

                <?php foreach ($cart as $key => $row): ?>

                    <?php

                    // Bỏ qua dòng không chứa mảng dữ liệu hợp lệ.
                    if (!is_array($row)) {
                        continue;
                    }


                    /*
                     * Lấy dữ liệu của sản phẩm hiện tại.
                     *
                     * Các tên trường phải khớp với cart.php.
                     * Nếu trường nào không tồn tại thì dùng giá trị
                     * mặc định để tránh lỗi PHP.
                     */

                    $name = (string)(
                        $row['name'] ?? 'Sản phẩm La Muse'
                    );

                    $image = trim(
                        (string)($row['image'] ?? '')
                    );

                    $sku = (string)($row['sku'] ?? '');

                    $color = (string)(
                        $row['color_name'] ?? ''
                    );

                    $size = (string)(
                        $row['size_name'] ?? ''
                    );

                    $price = max(
                        0,
                        (float)($row['price'] ?? 0)
                    );

                    $quantity = max(
                        0,
                        (int)($row['quantity'] ?? 0)
                    );


                    /*
                     * Tính thành tiền của sản phẩm hiện tại.
                     *
                     * Ví dụ:
                     * Đơn giá 650.000₫
                     * Số lượng 2
                     * Thành tiền 1.300.000₫
                     */
                    $lineTotal = $price * $quantity;

                    ?>


                    <!-- Một khối thông tin của một sản phẩm -->
                    <div class="summary-product">


                        <!--
                        PHẦN 9.3. HIỂN THỊ ẢNH SẢN PHẨM

                        Nếu session có đường dẫn ảnh thì hiển thị ảnh.
                        Nếu không có đường dẫn ảnh thì hiện chữ LM.

                        Đường dẫn ảnh được sử dụng nguyên trạng từ giỏ
                        hàng, không tự nối thêm đường dẫn thư mục.
                        -->

                        <?php if ($image !== ''): ?>

                            <img
                                src="<?= lm_checkout_h($image) ?>"
                                alt="<?= lm_checkout_h($name) ?>"
                                class="summary-product-image"
                                loading="lazy"
                                style="width:76px;height:92px;object-fit:cover;flex-shrink:0;"
                            >

                        <?php else: ?>

                            <div class="product-placeholder">
                                LM
                            </div>

                        <?php endif; ?>


                        <!--
                        PHẦN 9.4. HIỂN THỊ THÔNG TIN SẢN PHẨM
                        -->

                        <div class="product-info">


                            <!-- Tên sản phẩm lấy từ $row['name'] -->
                            <h3>
                                <?= lm_checkout_h($name) ?>
                            </h3>


                            <!-- Chỉ hiển thị mã SKU nếu có -->
                            <?php if ($sku !== ''): ?>

                                <p>
                                    Mã: <?= lm_checkout_h($sku) ?>
                                </p>

                            <?php endif; ?>


                            <!-- Hiển thị màu sắc và kích thước -->
                            <?php if ($color !== '' || $size !== ''): ?>

                                <p>

                                    <?php if ($color !== ''): ?>
                                        Màu:
                                        <?= lm_checkout_h($color) ?>
                                    <?php endif; ?>

                                    <?php if ($color !== '' && $size !== ''): ?>
                                        ·
                                    <?php endif; ?>

                                    <?php if ($size !== ''): ?>
                                        Size:
                                        <?= lm_checkout_h($size) ?>
                                    <?php endif; ?>

                                </p>

                            <?php endif; ?>


                            <!-- Số lượng khách đã chọn -->
                            <p>
                                Số lượng: <?= $quantity ?>
                            </p>


                            <!-- Đơn giá của một sản phẩm -->
                            <span>
                                <?= lm_checkout_money($price) ?>
                            </span>


                            <!--
                            Thành tiền của riêng dòng sản phẩm.
                            Không phải tổng tiền của cả đơn hàng.
                            -->

                            <p class="summary-line-total">

                                Thành tiền:

                                <strong>
                                    <?= lm_checkout_money($lineTotal) ?>
                                </strong>

                            </p>

                        </div>

                    </div>

                <?php endforeach; ?>


                <!-- Đường kẻ ngăn cách danh sách sản phẩm và tiền -->
                <div class="summary-divider"></div>


                <!--
                PHẦN 9.5. HIỂN THỊ TẠM TÍNH

                $subtotal đã được tính ở PHẦN 5.

                Đây là tổng thành tiền của tất cả sản phẩm,
                chưa bao gồm phí vận chuyển.
                -->

                <div class="summary-row">

                    <span>
                        Tạm tính (<?= $totalQuantity ?> sản phẩm)
                    </span>

                    <span>
                        <?= lm_checkout_money($subtotal) ?>
                    </span>

                </div>


                <!--
                PHẦN 9.6. HIỂN THỊ PHÍ VẬN CHUYỂN

                Giá trị lấy từ biến $shippingFee ở PHẦN 6.
                Không cần viết cố định 30.000₫ trong HTML nữa.
                -->

                <div class="summary-row">

                    <span>Phí vận chuyển</span>

                    <span>
                        <?= lm_checkout_money($shippingFee) ?>
                    </span>

                </div>


                <!-- Đường kẻ phía trên tổng thanh toán -->
                <div class="summary-divider"></div>


                <!--
                PHẦN 9.7. TỔNG THANH TOÁN

                $grandTotal = $subtotal + $shippingFee.

                Đây là tổng tiền khách cần thanh toán theo
                quy tắc tính phí hiện tại của website.
                -->

                <div class="summary-row total-row">

                    <strong>Tổng thanh toán</strong>

                    <strong>
                        <?= lm_checkout_money($grandTotal) ?>
                    </strong>

                </div>


                <!-- Lời cảm ơn cố định của thương hiệu -->
                <p class="summary-note">
                    Cảm ơn nàng đã lựa chọn La Muse.
                </p>


            <?php endif; ?>

        </aside>

    </div>

</main>

</body>
</html>
