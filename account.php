<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php?next=account.php');
    exit;
}

function account_h(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function account_money(mixed $value): string {
    return number_format((float)$value, 0, ',', '.') . '₫';
}
function account_status_label(string $status): string {
    return [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy',
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'refunded' => 'Đã hoàn tiền',
    ][$status] ?? $status;
}

if (empty($_SESSION['account_csrf'])) {
    $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['account_csrf'];
$error = '';
$success = '';
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($csrf, $token)) {
            http_response_code(419);
            exit('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'logout') {
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['lm_after_login'], $_SESSION['account_csrf']);
            session_regenerate_id(true);
            header('Location: login.php');
            exit;
        }

        if ($action === 'save_profile') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $diaChi = trim((string)($_POST['dia_chi'] ?? ''));
            $phuongXa = trim((string)($_POST['phuong_xa'] ?? ''));
            $tinhThanh = trim((string)($_POST['tinh_thanh'] ?? ''));

            if ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 100) {
                $error = 'Nàng vui lòng nhập họ tên (tối đa 100 ký tự) nhé.';
            } elseif ($phone !== '' && !preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
                $error = 'Số điện thoại chưa đúng định dạng. Nàng kiểm tra lại nhé.';
            } elseif (mb_strlen($diaChi, 'UTF-8') > 255 || mb_strlen($phuongXa, 'UTF-8') > 100 || mb_strlen($tinhThanh, 'UTF-8') > 100) {
                $error = 'Địa chỉ hơi dài rồi nàng ơi, vui lòng kiểm tra lại nhé.';
            } else {
                $update = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, dia_chi = :dia_chi, phuong_xa = :phuong_xa, tinh_thanh = :tinh_thanh WHERE id = :id');
                $update->execute([
                    'full_name' => $fullName,
                    'phone' => $phone !== '' ? $phone : null,
                    'dia_chi' => $diaChi !== '' ? $diaChi : null,
                    'phuong_xa' => $phuongXa !== '' ? $phuongXa : null,
                    'tinh_thanh' => $tinhThanh !== '' ? $tinhThanh : null,
                    'id' => $userId,
                ]);
                $_SESSION['user_name'] = $fullName;
                header('Location: account.php?saved=1');
                exit;
            }
            $editMode = true;
        }
    }

    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, dia_chi, phuong_xa, tinh_thanh, status, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || ($user['status'] ?? '') !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['lm_after_login']);
        header('Location: login.php');
        exit;
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $success = 'Thông tin của nàng đã được cập nhật thành công.';
    }

    // Lịch sử đơn hàng sử dụng đúng cấu trúc bảng trong database lamuse.
    $orderStmt = $pdo->prepare(
        'SELECT id, ma_don_hang, tong_tien, phi_van_chuyen, giam_gia, thanh_tien,
                phuong_thuc_thanh_toan, trang_thai_thanh_toan, trang_thai_don_hang, ngay_dat
         FROM don_hang WHERE user_id = :user_id ORDER BY ngay_dat DESC, id DESC LIMIT 10'
    );
    $orderStmt->execute(['user_id' => $userId]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    $orderItems = [];
    if ($orders) {
        $orderIds = array_map(static fn($order) => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $pdo->prepare(
            "SELECT don_hang_id, ten_san_pham, ma_san_pham, ten_mau, ten_size, don_gia, so_luong, thanh_tien
             FROM chi_tiet_don_hang WHERE don_hang_id IN ($placeholders) ORDER BY id ASC"
        );
        $itemsStmt->execute($orderIds);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderItems[(int)$item['don_hang_id']][] = $item;
        }
    }
} catch (PDOException $e) {
    // Keep the account page usable if order tables have not been created yet.
    if (!isset($user) || !$user) {
        http_response_code(500);
        exit('Chưa thể tải thông tin tài khoản. Nàng kiểm tra kết nối database rồi thử lại nhé.');
    }
    $orders = $orders ?? [];
    $orderItems = $orderItems ?? [];
    $orderHistoryUnavailable = true;
}
$orderHistoryUnavailable = $orderHistoryUnavailable ?? false;

$name = trim((string)($user['full_name'] ?? ''));
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($name, 0, 1));
$joinedDate = !empty($user['created_at']) ? date('d/m/Y', strtotime((string)$user['created_at'])) : 'Chưa cập nhật';
$memberSince = !empty($user['created_at']) ? date('Y', strtotime((string)$user['created_at'])) : date('Y');
$displayName = $editMode && isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : $name;
$displayPhone = $editMode && isset($_POST['phone']) ? trim((string)$_POST['phone']) : (string)($user['phone'] ?? '');
$displayDiaChi = $editMode && isset($_POST['dia_chi']) ? trim((string)$_POST['dia_chi']) : (string)($user['dia_chi'] ?? '');
$displayPhuongXa = $editMode && isset($_POST['phuong_xa']) ? trim((string)$_POST['phuong_xa']) : (string)($user['phuong_xa'] ?? '');
$displayTinhThanh = $editMode && isset($_POST['tinh_thanh']) ? trim((string)$_POST['tinh_thanh']) : (string)($user['tinh_thanh'] ?? '');
$addressParts = array_filter([$user['dia_chi'] ?? '', $user['phuong_xa'] ?? '', $user['tinh_thanh'] ?? ''], static fn($part) => trim((string)$part) !== '');
$displayAddress = implode(', ', $addressParts);
?>
<?php require_once __DIR__ . '/header.php'; ?>

<style>

:root{--paper:#f7f4eb;--cream:#fbf9f3;--sage:#e5e6d5;--sage-dark:#626b4b;--olive:#4d573b;--ink:#34352b;--muted:#858071;--line:#e1ddcf;--serif:'Cormorant Garamond',Georgia,serif;--sans:'DM Sans',sans-serif;--script:'Parisienne',cursive}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:24px}body{margin:0;background:radial-gradient(ellipse at 10% 25%,rgba(226,221,199,.25),transparent 36%),var(--paper);color:var(--ink);font:13px var(--sans);-webkit-font-smoothing:antialiased}a{color:inherit;text-decoration:none}button,input{font:inherit}button,a{-webkit-tap-highlight-color:transparent}


.hero {
    height: 180px !important;
    min-height: 180px !important;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 20px max(7.6vw, 26px);
    background-color: #e8e1ce;
    background-image:
        linear-gradient(
            90deg,
            rgba(248,245,235,.97) 0%,
            rgba(248,245,235,.90) 42%,
            rgba(248,245,235,.45) 100%
        ),
        url('images/banner_account.jpg');
    background-position: center, center 45%;
    background-size: cover;
    background-repeat: no-repeat;
    border-bottom: 1px solid #e2ddce;
}

.hero-copy {
    max-width: 650px;
    position: relative;
    z-index: 1;
}

.hero h1 {
    margin: 0;
    color: #343629;
    font: italic 500 clamp(30px, 3.5vw, 42px)/1.1 var(--serif);
    letter-spacing: -.4px;
}

.hero-description {
    margin: 10px 0 0;
    color: #514f43;
    font: 16px/1.5 var(--serif);
}


.layout{width:min(1200px,calc(100% - 48px));margin:38px auto 0;display:grid;grid-template-columns:225px minmax(0,1fr);align-items:start;gap:28px}.sidebar{padding-top:10px}.sidebar-label{padding:0 15px;margin:0 0 13px;color:#99917e;font-size:9px;letter-spacing:2px;text-transform:uppercase}.side-link{min-height:46px;padding:12px 14px;margin-bottom:4px;display:flex;align-items:center;gap:13px;border-radius:5px;color:#696858;font:16px var(--serif);transition:background .2s,color .2s}.side-link svg{width:19px;height:19px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.35}.side-link:hover,.side-link.active{background:#e8e8d9;color:var(--olive)}.side-link.logout{width:100%;border:0;background:transparent;text-align:left;cursor:pointer}.sidebar-note{margin:60px 0 0 8px;color:#7a8064;font:25px var(--script);transform:rotate(-5deg)}
.content{min-width:0;display:grid;gap:22px}.account-card,.orders-card{min-width:0;padding:clamp(22px,3vw,36px);background:rgba(251,249,243,.72);border:1px solid var(--line);border-radius:5px;box-shadow:0 8px 35px rgba(90,77,52,.025)}.profile-head{display:flex;align-items:center;gap:18px;padding-bottom:25px;border-bottom:1px solid var(--line)}.avatar{display:grid;place-items:center;flex:0 0 70px;width:70px;height:70px;border-radius:50%;border:1px solid #d7d5c1;background:radial-gradient(circle at 30% 25%,#f8f3e4,transparent 55%),#e4e3d2;color:var(--olive);font:italic 34px var(--serif)}.profile-main{min-width:0}.profile-name-row{display:flex;align-items:center;flex-wrap:wrap;gap:10px}.profile-name{margin:0;font:500 30px/1.15 var(--serif);overflow-wrap:anywhere}.verified{padding:5px 10px;border-radius:30px;background:#e6e6d4;color:#666d4e;font:12px var(--serif);white-space:nowrap}.profile-email{margin:7px 0 0;color:#817c6e;font-size:12px;overflow-wrap:anywhere}.profile-quote{margin-left:auto;padding-left:15px;color:#777c61;font:22px/1.25 var(--script);text-align:right}
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;margin:0 0 20px}.section-title{margin:0;font:600 25px var(--serif)}.personal-section{padding:25px 0 0}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}.info-column+.info-column{padding-left:24px;border-left:1px solid var(--line)}.info-item{display:grid;grid-template-columns:minmax(105px,.8fr) minmax(0,1.2fr);gap:12px;align-items:start;padding:0 0 20px}.info-item:last-child{padding-bottom:0}.info-label{color:#827d6f;font:15px var(--serif)}.info-value{color:#48483d;font:15px/1.5 var(--serif);overflow-wrap:anywhere}.status,.pill{display:inline-block;padding:5px 11px;border-radius:30px;background:#e5e7d5;color:#5b6745;font:13px var(--serif);line-height:1.2}.member-note{display:flex;align-items:center;gap:15px;margin-top:24px;padding:19px 20px;background:linear-gradient(100deg,#e9e9dc,#f0efe5);border:1px solid #e4e3d5;border-radius:5px}.member-icon{flex-shrink:0;color:var(--olive)}.member-icon svg{display:block;width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.25}.member-copy{flex:1}.member-title{margin:0;color:#484b37;font:600 17px var(--serif)}.member-description{margin:4px 0 0;color:#7c7b6b;font:14px/1.5 var(--serif)}
.actions{display:flex;align-items:center;flex-wrap:wrap;gap:11px;margin-top:23px}.btn{min-height:42px;padding:11px 18px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--olive);border-radius:3px;font:16px var(--serif);cursor:pointer;transition:background .2s,color .2s,transform .2s}.btn:hover{transform:translateY(-1px)}.btn-primary{background:var(--olive);color:#fffdf5}.btn-primary:hover{background:#39432d}.btn-secondary{background:transparent;color:var(--olive)}.btn-secondary:hover{background:#e8e8d9}.btn-small{min-height:35px;padding:7px 13px;font-size:14px}
.alert{padding:12px 15px;margin:0 0 18px;border:1px solid;border-radius:4px;line-height:1.5}.alert-success{background:#edf0e3;border-color:#d8dfc8;color:#4e603c}.alert-error{background:#f8e9e3;border-color:#e9c9bd;color:#8b4636}
.edit-form{display:grid;grid-template-columns:1fr 1fr;gap:17px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{color:#696858;font:15px var(--serif)}.field input,.field select{width:100%;min-height:43px;padding:11px 12px;border:1px solid #dcd8c9;border-radius:3px;background:#fffdf7;color:var(--ink);outline:none}.field input:focus,.field select:focus{border-color:var(--sage-dark);box-shadow:0 0 0 3px rgba(98,107,75,.1)}.field input[readonly]{background:#f0ede3;color:#817c6e}.form-hint{margin:0;color:var(--muted);font-size:11px}
.orders-card{padding-bottom:26px}.orders-intro{margin:-11px 0 20px;color:var(--muted);font:15px var(--serif)}.order-list{display:grid;gap:13px}.order-item{border:1px solid #e4dfd1;border-radius:4px;background:rgba(255,253,247,.6);overflow:hidden}.order-top{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;padding:16px 17px;background:#f2efe5}.order-number{margin:0;color:var(--olive);font:600 17px var(--serif);overflow-wrap:anywhere}.order-date{margin:5px 0 0;color:var(--muted);font-size:11px}.order-total{font:600 18px var(--serif);white-space:nowrap}.order-details{padding:13px 17px 15px}.order-products{display:grid;gap:7px;margin-bottom:12px}.order-product{display:flex;justify-content:space-between;gap:15px;color:#686657;font:14px/1.4 var(--serif)}.order-product-name{min-width:0}.order-product-price{white-space:nowrap}.order-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:10px}.status-cancelled{background:#f2e4df;color:#965545}.status-delivered{background:#e2ebdc;color:#48633e}.status-shipping{background:#e3e8ef;color:#4f6482}.status-pending{background:#f3ead4;color:#806735}.payment-note{color:#858071;font-size:11px}.empty-state{text-align:center;padding:28px 16px;border:1px dashed #d9d5c6;border-radius:4px;color:#777565}.empty-icon{font:36px var(--serif);color:#858b6c}.empty-state h3{margin:7px 0;font:600 22px var(--serif);color:#4c4d3d}.empty-state p{margin:0 0 15px;font:15px/1.5 var(--serif)}
.bottom-signature{width:min(1200px,calc(100% - 48px));margin:34px auto 26px;display:flex;align-items:center;justify-content:space-between;gap:20px;color:#85876c;font:25px var(--script)}.signature-right{font:italic 14px var(--serif);text-align:right}
@media(min-width:1500px){.layout,.bottom-signature{width:min(1280px,calc(100% - 100px))}.layout{grid-template-columns:245px minmax(0,1fr);gap:32px}.hero{min-height:260px}}
@media(max-width:900px){.layout{grid-template-columns:185px minmax(0,1fr);gap:18px}.profile-quote{display:none}.info-grid{grid-template-columns:1fr;gap:18px}.info-column+.info-column{padding:18px 0 0;border-left:0;border-top:1px solid var(--line)}}
@media(max-width:620px){.hero{min-height:210px;padding:32px 24px;background-position:center,58% center}.hero h1{font-size:42px}.hero-description{max-width:280px;font-size:17px}.hero:after{right:-7%;font-size:125px}.layout{width:calc(100% - 28px);margin-top:22px;grid-template-columns:minmax(0,1fr);gap:18px}.sidebar{display:flex;gap:7px;overflow-x:auto;padding:0 0 5px;scrollbar-width:thin}.sidebar-label,.sidebar-note{display:none}.side-link{flex:0 0 auto;min-height:39px;padding:9px 12px;margin:0;gap:7px;border:1px solid #e2dfd0;font-size:14px;white-space:nowrap}.side-link svg{width:16px;height:16px}.account-card,.orders-card{padding:20px 17px}.profile-head{gap:13px;padding-bottom:21px}.avatar{flex-basis:56px;width:56px;height:56px;font-size:28px}.profile-name{font-size:26px}.profile-email{font-size:11px}.verified{font-size:11px;padding:4px 8px}.personal-section{padding-top:22px}.section-title{font-size:23px}.info-item{grid-template-columns:minmax(105px,.85fr) minmax(0,1.15fr);gap:9px;padding-bottom:17px}.info-label,.info-value{font-size:14px}.member-note{padding:15px 13px;gap:11px}.member-title{font-size:16px}.member-description{font-size:13px}.btn{flex:1 1 auto;padding:10px 12px;font-size:15px}.edit-form{grid-template-columns:1fr}.field.full{grid-column:auto}.order-top{padding:13px;gap:10px}.order-details{padding:12px 13px}.order-number{font-size:16px}.order-total{font-size:16px}.order-product{font-size:13px}.bottom-signature{width:calc(100% - 32px);margin:27px auto 22px;font-size:22px}.signature-right{font-size:12px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}

</style>

<section class="hero"><div class="hero-copy"><p class="eyebrow">Espace personnel · La Muse</p><h1>Chào mừng trở lại, <?= account_h($name) ?>.</h1><p class="hero-description">Một góc nhỏ lưu giữ những điều thân thuộc của nàng.</p></div></section>
<main class="layout">
<aside class="sidebar" aria-label="Menu tài khoản">
  <p class="sidebar-label">Không gian của nàng</p>
  <a class="side-link active" href="account.php" aria-current="page"><svg viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="4"/><path d="M4.5 21v-2.2a7.5 7.5 0 0 1 15 0V21z"/></svg>Thông tin tài khoản</a>
  <a class="side-link" href="#order-history"><svg viewBox="0 0 24 24"><path d="M4 5h16v16H4zM8 3v4M16 3v4M4 10h16"/><path d="m8 15 2 2 5-5"/></svg>Lịch sử đơn hàng</a>
  <a class="side-link" href="products.php"><svg viewBox="0 0 24 24"><path d="M5 8h14l1 13H4z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></svg>Khám phá sản phẩm</a>
  <form method="post" action="account.php" style="margin:0"><input type="hidden" name="csrf_token" value="<?= account_h($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="side-link logout" type="submit"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>Đăng xuất</button></form>
  <div class="sidebar-note">Merci d'être ici ♡</div>
</aside>
<div class="content">
<section class="account-card">
  <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= account_h($success) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= account_h($error) ?></div><?php endif; ?>
  <div class="profile-head">
    <div class="avatar" aria-hidden="true"><?= account_h($initial ?: 'L') ?></div>
    <div class="profile-main"><div class="profile-name-row"><h2 class="profile-name"><?= account_h($name) ?></h2><span class="verified">Thành viên La Muse</span></div><p class="profile-email"><?= account_h($user['email']) ?></p></div>
    <div class="profile-quote">La vie est plus belle<br>quand on vit ses rêves.</div>
  </div>
  <section class="personal-section" id="personal-info">
    <div class="section-heading"><h2 class="section-title"><?= $editMode ? 'Chỉnh sửa thông tin' : 'Thông tin cá nhân' ?></h2>
    <?php if (!$editMode): ?><a class="btn btn-secondary btn-small" href="account.php?edit=1">✎ Chỉnh sửa</a><?php endif; ?></div>
    <?php if ($editMode): ?>
      <form method="post" action="account.php?edit=1" class="edit-form">
        <input type="hidden" name="csrf_token" value="<?= account_h($csrf) ?>"><input type="hidden" name="action" value="save_profile">
        <div class="field"><label for="full_name">Họ và tên *</label><input id="full_name" name="full_name" type="text" maxlength="100" autocomplete="name" required value="<?= account_h($displayName) ?>"></div>
        <div class="field"><label for="phone">Số điện thoại</label><input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= account_h($displayPhone) ?>" placeholder="Ví dụ: 0901 234 567"><p class="form-hint">Dùng để liên hệ khi nàng đặt hàng.</p></div>
        <div class="field full"><label for="dia_chi">Địa chỉ nhận hàng</label><input id="dia_chi" name="dia_chi" type="text" maxlength="255" autocomplete="street-address" value="<?= account_h($displayDiaChi) ?>" placeholder="Số nhà, tên đường..."></div>
        <div class="field"><label for="phuong_xa">Phường / xã</label><input id="phuong_xa" name="phuong_xa" type="text" maxlength="100" value="<?= account_h($displayPhuongXa) ?>" autocomplete="address-level3" placeholder="Tên phường / xã hiện nay"></div>
        <div class="field"><label for="tinh_thanh">Tỉnh / thành phố</label>
          <?php $provinces = ['An Giang','Bắc Ninh','Cà Mau','Cao Bằng','Đắk Lắk','Điện Biên','Đồng Nai','Đồng Tháp','Gia Lai','Hà Tĩnh','Hưng Yên','Khánh Hòa','Lai Châu','Lâm Đồng','Lạng Sơn','Lào Cai','Nghệ An','Ninh Bình','Phú Thọ','Quảng Ngãi','Quảng Ninh','Quảng Trị','Sơn La','Tây Ninh','Thái Nguyên','Thanh Hóa','Thành phố Cần Thơ','Thành phố Đà Nẵng','Thành phố Hà Nội','Thành phố Hải Phòng','Thành phố Huế','Thành phố Hồ Chí Minh','Tuyên Quang','Vĩnh Long']; ?>
          <select id="tinh_thanh" name="tinh_thanh" autocomplete="address-level1" required>
            <option value="">Chọn tỉnh / thành phố</option>
            <?php if ($displayTinhThanh !== '' && !in_array($displayTinhThanh, $provinces, true)): ?><option value="<?= account_h($displayTinhThanh) ?>" selected><?= account_h($displayTinhThanh) ?> (địa chỉ đã lưu)</option><?php endif; ?>
            <?php foreach ($provinces as $province): ?><option value="<?= account_h($province) ?>" <?= $displayTinhThanh === $province ? 'selected' : '' ?>><?= account_h($province) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field full"><label for="email">Địa chỉ email</label><input id="email" type="email" readonly value="<?= account_h($user['email']) ?>"><p class="form-hint">Email đăng nhập hiện chưa thể thay đổi tại đây.</p></div>
        <div class="actions field full"><button class="btn btn-primary" type="submit">Lưu thay đổi <span aria-hidden="true">→</span></button><a class="btn btn-secondary" href="account.php">Hủy</a></div>
      </form>
    <?php else: ?>
      <div class="info-grid">
        <div class="info-column">
          <div class="info-item"><div class="info-label">Họ và tên</div><div class="info-value"><?= account_h($name) ?></div></div>
          <div class="info-item"><div class="info-label">Địa chỉ email</div><div class="info-value"><?= account_h($user['email']) ?></div></div>
          <div class="info-item"><div class="info-label">Số điện thoại</div><div class="info-value"><?= account_h($user['phone'] ?: 'Chưa cập nhật') ?></div></div>
          <div class="info-item"><div class="info-label">Địa chỉ nhận hàng</div><div class="info-value"><?= account_h($displayAddress !== '' ? $displayAddress : 'Chưa cập nhật') ?></div></div>
        </div>
        <div class="info-column">
          <div class="info-item"><div class="info-label">Trạng thái</div><div class="info-value"><span class="status">Đang hoạt động</span></div></div>
          <div class="info-item"><div class="info-label">Ngày tham gia</div><div class="info-value"><?= account_h($joinedDate) ?></div></div>
          <div class="info-item"><div class="info-label">Thành viên từ</div><div class="info-value"><?= account_h($memberSince) ?></div></div>
        </div>
      </div>
    <?php endif; ?>
  </section>
  <div class="member-note"><div class="member-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="1.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></div><div class="member-copy"><p class="member-title">Một góc nhỏ dành riêng cho nàng</p><p class="member-description">Thông tin của nàng được dùng để hỗ trợ trải nghiệm mua sắm và liên hệ về đơn hàng tại La Muse.</p></div></div>
  <div class="actions"><a class="btn btn-primary" href="products.php">Khám phá bộ sưu tập <span aria-hidden="true">→</span></a><a class="btn btn-secondary" href="index.php">Về trang chủ</a></div>
</section>

<section class="orders-card" id="order-history">
  <div class="section-heading"><h2 class="section-title">Lịch sử đơn hàng</h2><span class="pill"><?= isset($orders) ? count($orders) . ' đơn gần đây' : 'Đơn hàng' ?></span></div>
  <p class="orders-intro">Những món đồ nàng đã chọn và hành trình đơn hàng tại La Muse.</p>
  <?php if ($orderHistoryUnavailable): ?>
    <div class="empty-state"><div class="empty-icon">♡</div><h3>Lịch sử đơn hàng đang được hoàn thiện</h3><p>Hiện chưa thể tải lịch sử đơn hàng. Nàng vẫn có thể tiếp tục mua sắm và quay lại sau nhé.</p><a class="btn btn-secondary btn-small" href="products.php">Khám phá sản phẩm</a></div>
  <?php elseif (empty($orders)): ?>
    <div class="empty-state"><div class="empty-icon">⋆｡ﾟ☁︎｡⋆｡ ﾟ☾ ﾟ｡⋆</div><h3>Hành trình mua sắm của nàng bắt đầu từ đây</h3><p>Nàng chưa có đơn hàng nào. Hãy tìm một thiết kế thật hợp với mình nhé.</p><a class="btn btn-primary btn-small" href="products.php">Khám phá bộ sưu tập →</a></div>
  <?php else: ?>
    <div class="order-list">
    <?php foreach ($orders as $order):
      $orderStatus = (string)$order['trang_thai_don_hang'];
      $statusClass = match ($orderStatus) { 'cancelled' => 'status-cancelled', 'delivered' => 'status-delivered', 'shipping' => 'status-shipping', default => 'status-pending' };
      $paymentMethod = $order['phuong_thuc_thanh_toan'] === 'bank_transfer' ? 'Chuyển khoản ngân hàng' : 'Thanh toán khi nhận hàng (COD)';
      $orderId = (int)$order['id'];
    ?>
      <article class="order-item">
        <div class="order-top"><div><h3 class="order-number">Đơn hàng #<?= account_h($order['ma_don_hang']) ?></h3><p class="order-date">Đặt ngày <?= account_h(date('d/m/Y · H:i', strtotime((string)$order['ngay_dat']))) ?></p></div><div class="order-total"><?= account_money($order['thanh_tien']) ?></div></div>
        <div class="order-details">
          <?php if (!empty($orderItems[$orderId])): ?><div class="order-products"><?php foreach ($orderItems[$orderId] as $item): ?><div class="order-product"><span class="order-product-name"><?= account_h($item['ten_san_pham']) ?><?php if (!empty($item['ten_mau']) || !empty($item['ten_size'])): ?><span class="payment-note"> · <?= account_h(trim(($item['ten_mau'] ?? '') . ' ' . ($item['ten_size'] ?? ''))) ?></span><?php endif; ?> × <?= (int)$item['so_luong'] ?></span><span class="order-product-price"><?= account_money($item['thanh_tien']) ?></span></div><?php endforeach; ?></div><?php else: ?><p class="payment-note">Chi tiết sản phẩm chưa có.</p><?php endif; ?>
          <div class="order-meta"><span class="pill <?= account_h($statusClass) ?>"><?= account_h(account_status_label($orderStatus)) ?></span><span class="pill"><?= account_h(account_status_label((string)$order['trang_thai_thanh_toan'])) ?></span><span class="payment-note"><?= account_h($paymentMethod) ?></span></div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
</div>
</main>
<div class="bottom-signature"><span>𐔌՞. .՞𐦯 Merci d'être ici</span><span class="signature-right">Une élégance intemporelle.</span></div>

<?php require_once __DIR__ . '/footer.php'; ?>
