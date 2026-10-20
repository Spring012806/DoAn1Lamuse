<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;
$pageTitle = $editing ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
$active = 'products';
$error = '';
$product = [
    'danh_muc_id'=>'', 'ma_san_pham'=>'', 'ten_san_pham'=>'', 'slug'=>'',
    'mo_ta'=>'', 'chat_lieu'=>'', 'gia_niem_yet'=>'', 'gia_khuyen_mai'=>'',
    'anh_dai_dien'=>'', 'noi_bat'=>0, 'trang_thai'=>'dang_ban'
];
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM sanpham WHERE id=?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { http_response_code(404); exit('Không tìm thấy sản phẩm.'); }
    $product = $found;
}
$categories = $pdo->query('SELECT id,ten_danh_muc FROM danhmuc WHERE trang_thai=1 ORDER BY ten_danh_muc')->fetchAll();
$gallery = [];
if ($editing) {
    $stmt = $pdo->prepare('SELECT id,duong_dan,mo_ta_anh,thu_tu,anh_chinh FROM hinhanh_sanpham WHERE san_pham_id=? ORDER BY anh_chinh DESC,thu_tu,id');
    $stmt->execute([$id]);
    $gallery = $stmt->fetchAll();
}

function upload_gallery_files(array $files): array {
    if (empty($files['name']) || !is_array($files['name'])) return [];
    $saved = [];
    foreach ($files['name'] as $i => $originalName) {
        $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $one = [
            'name'=>$originalName,
            'type'=>$files['type'][$i] ?? '',
            'tmp_name'=>$files['tmp_name'][$i] ?? '',
            'error'=>$err,
            'size'=>$files['size'][$i] ?? 0
        ];
        $path = upload_product_image($one);
        if ($path) $saved[] = $path;
    }
    return $saved;
}


/** Tạo mã sản phẩm chung, không phụ thuộc danh mục: LM-SP-000001, LM-SP-000002... */
function generate_product_code(PDO $pdo): string {
    $codes = $pdo->query("SELECT ma_san_pham FROM sanpham WHERE ma_san_pham LIKE 'LM-SP-%'")->fetchAll(PDO::FETCH_COLUMN);
    $max = 0;
    foreach ($codes as $code) {
        if (preg_match('/^LM-SP-(\d+)$/', (string)$code, $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }
    $candidateNumber = $max + 1;
    $check = $pdo->prepare('SELECT 1 FROM sanpham WHERE ma_san_pham = ? LIMIT 1');
    do {
        $candidate = 'LM-SP-' . str_pad((string)$candidateNumber, 6, '0', STR_PAD_LEFT);
        $check->execute([$candidate]);
        $exists = (bool)$check->fetchColumn();
        $candidateNumber++;
    } while ($exists);
    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['danh_muc_id','ten_san_pham','slug','mo_ta','chat_lieu','gia_niem_yet','gia_khuyen_mai','trang_thai'] as $key) {
        $product[$key] = $_POST[$key] ?? $product[$key];
    }
    $product['ma_san_pham'] = trim((string)$product['ma_san_pham']);
    $product['ten_san_pham'] = trim((string)$product['ten_san_pham']);
    $product['slug'] = trim((string)$product['slug']) ?: slugify($product['ten_san_pham']);
    $product['noi_bat'] = isset($_POST['noi_bat']) ? 1 : 0;
    try {
        if ($product['ten_san_pham'] === '') {
            throw new RuntimeException('Vui lòng nhập tên sản phẩm.');
        }

        // Tự tạo mã khi thêm sản phẩm mới.
        if (!$editing && $product['ma_san_pham'] === '') {
            $product['ma_san_pham'] = generate_product_code($pdo);
        }

        if ($product['ma_san_pham'] === '') {
            throw new RuntimeException('Không tạo được mã sản phẩm. Vui lòng thử lại.');
        }
        if (!is_numeric($product['danh_muc_id']) || (int)$product['danh_muc_id'] < 1) throw new RuntimeException('Vui lòng chọn danh mục.');
        $categoryCheck = $pdo->prepare(
    'SELECT id FROM danhmuc WHERE id = ? AND trang_thai = 1'
);

$categoryCheck->execute([
    (int)$product['danh_muc_id']
]);

if (!$categoryCheck->fetchColumn()) {
    throw new RuntimeException(
        'Danh mục không tồn tại hoặc đã ngừng hoạt động.'
    );
}
        if (!is_numeric($product['gia_niem_yet']) || (float)$product['gia_niem_yet'] < 0) throw new RuntimeException('Giá niêm yết không hợp lệ.');

if (
    $product['gia_khuyen_mai'] !== ''
    && (
        !is_numeric($product['gia_khuyen_mai'])
        || (float)$product['gia_khuyen_mai'] <= 0
    )
) {
    throw new RuntimeException('Giá khuyến mãi phải là số lớn hơn 0.');
}

if (
    $product['gia_khuyen_mai'] !== ''
    && (float)$product['gia_khuyen_mai'] >= (float)$product['gia_niem_yet']
) {
    throw new RuntimeException('Giá khuyến mãi phải thấp hơn giá niêm yết.');
}

        if (!in_array($product['trang_thai'], ['dang_ban','an','ngung_kinh_doanh'], true)) throw new RuntimeException('Trạng thái không hợp lệ.');

        $oldCover = $product['anh_dai_dien'] ?: null;
        $newCover = upload_product_image($_FILES['anh_dai_dien'] ?? []);
        $coverPath = $newCover ?? $oldCover;
        $values = [
            (int)$product['danh_muc_id'], $product['ma_san_pham'], $product['ten_san_pham'], $product['slug'],
            $product['mo_ta'], $product['chat_lieu'], (float)$product['gia_niem_yet'],
            $product['gia_khuyen_mai'] === '' ? null : (float)$product['gia_khuyen_mai'],
            $coverPath, $product['noi_bat'], $product['trang_thai']
        ];
        if ($editing) {
            $values[] = $id;
            $pdo->prepare('UPDATE sanpham SET danh_muc_id=?,ma_san_pham=?,ten_san_pham=?,slug=?,mo_ta=?,chat_lieu=?,gia_niem_yet=?,gia_khuyen_mai=?,anh_dai_dien=?,noi_bat=?,trang_thai=? WHERE id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO sanpham (danh_muc_id,ma_san_pham,ten_san_pham,slug,mo_ta,chat_lieu,gia_niem_yet,gia_khuyen_mai,anh_dai_dien,noi_bat,trang_thai) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
            $editing = true;
        }
        if ($newCover && $oldCover) delete_local_image($oldCover);

        // Xóa ảnh phụ được chọn ngay trong form sản phẩm.
        $deleteIds = array_values(array_filter(array_map('intval', (array)($_POST['delete_gallery'] ?? [])), fn($v) => $v > 0));
        if ($deleteIds) {
            $marks = implode(',', array_fill(0, count($deleteIds), '?'));
            $params = array_merge([$id], $deleteIds);
            $q = $pdo->prepare("SELECT id,duong_dan FROM hinhanh_sanpham WHERE san_pham_id=? AND id IN ($marks)");
            $q->execute($params);
            foreach ($q->fetchAll() as $row) {
                $pdo->prepare('DELETE FROM hinhanh_sanpham WHERE id=? AND san_pham_id=?')->execute([(int)$row['id'], $id]);
                delete_local_image($row['duong_dan']);
            }
        }

        $galleryPaths = upload_gallery_files($_FILES['anh_bo_sung'] ?? []);
        if ($galleryPaths) {
            $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(thu_tu),0)+1 FROM hinhanh_sanpham WHERE san_pham_id=' . $id)->fetchColumn();
            $insert = $pdo->prepare('INSERT INTO hinhanh_sanpham (san_pham_id,duong_dan,mo_ta_anh,thu_tu,anh_chinh) VALUES (?,?,?,?,0)');
            foreach ($galleryPaths as $path) $insert->execute([$id, $path, $product['ten_san_pham'], $nextOrder++]);
        }
        flash('success', 'Đã lưu sản phẩm và cập nhật hình ảnh. Bạn có thể quản lý biến thể và tồn kho từ danh sách sản phẩm.');
        redirect('product-form.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Dữ liệu bị trùng hoặc không phù hợp với cấu trúc CSDL (hãy kiểm tra mã sản phẩm và slug).' : $e->getMessage();
    }
    // Refresh gallery after a failed/successful partial operation.
    if ($editing) {
        $stmt = $pdo->prepare('SELECT id,duong_dan,mo_ta_anh,thu_tu,anh_chinh FROM hinhanh_sanpham WHERE san_pham_id=? ORDER BY anh_chinh DESC,thu_tu,id');
        $stmt->execute([$id]); $gallery = $stmt->fetchAll();
    }
}
require __DIR__ . '/admin-header.php';
?>
<section class="panel">
  <div class="panel-head"><div><h2>Thông tin sản phẩm</h2><div class="muted">Nhập thông tin và hình ảnh tại cùng một nơi. Các trường có dấu * là bắt buộc.</div></div></div>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="form-grid">
      <div class="field"><label>MÃ SẢN PHẨM</label><input value="<?= e($product['ma_san_pham']) ?>" readonly placeholder="Tự động tạo khi lưu sản phẩm"><small><?= $editing ? 'Mã được giữ cố định để tránh thay đổi định danh sản phẩm.' : 'Hệ thống sẽ tự tạo mã dạng LM-SP-000001 khi bạn lưu. Mã không phụ thuộc danh mục.' ?></small></div>
      <div class="field"><label>TÊN SẢN PHẨM *</label><input name="ten_san_pham" required value="<?= e($product['ten_san_pham']) ?>"></div>
      <div class="field"><label>DANH MỤC *</label><select name="danh_muc_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((string)$product['danh_muc_id'] === (string)$c['id']) ? 'selected' : '' ?>><?= e($c['ten_danh_muc']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>SLUG (đường dẫn)</label><input name="slug" value="<?= e($product['slug']) ?>" placeholder="Tự tạo từ tên nếu để trống"></div>
      <div class="field"><label>GIÁ NIÊM YẾT (VNĐ) *</label><input type="number" min="0" step="1000" name="gia_niem_yet" required value="<?= e($product['gia_niem_yet']) ?>"></div>
      <div class="field"><label>GIÁ KHUYẾN MÃI (VNĐ)</label><input type="number" min="0" step="1000" name="gia_khuyen_mai" value="<?= e($product['gia_khuyen_mai']) ?>" placeholder="Để trống nếu không giảm"></div>
      <div class="field"><label>CHẤT LIỆU</label><input name="chat_lieu" value="<?= e($product['chat_lieu']) ?>"></div>
      <div class="field"><label>TRẠNG THÁI</label><select name="trang_thai"><?php foreach (['dang_ban'=>'Đang bán','an'=>'Ẩn','ngung_kinh_doanh'=>'Ngừng kinh doanh'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= $product['trang_thai'] === $v ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field full"><label>MÔ TẢ</label><textarea name="mo_ta"><?= e($product['mo_ta']) ?></textarea></div>
      <div class="field"><label>ẢNH ĐẠI DIỆN (JPG/PNG/WEBP, tối đa 5 MB)</label><input type="file" name="anh_dai_dien" accept="image/jpeg,image/png,image/webp"><?php if ($product['anh_dai_dien']): ?><small>Ảnh hiện tại: <?= e($product['anh_dai_dien']) ?></small><div><img class="gallery-thumb" src="../<?= e($product['anh_dai_dien']) ?>" alt="Ảnh đại diện hiện tại"></div><?php endif; ?></div>
      <div class="field"><label>ĐÁNH DẤU NỔI BẬT</label><label style="font-weight:400"><input type="checkbox" name="noi_bat" value="1" <?= ((int)$product['noi_bat'] === 1) ? 'checked' : '' ?>> Hiển thị là sản phẩm nổi bật</label></div>
    </div>
    <div class="form-actions"><button class="btn">Lưu sản phẩm</button><a class="btn light" href="products.php">Quay lại danh sách</a></div>

    <div class="gallery-section">
      <div class="panel-head"><div><h2>Hình ảnh bổ sung</h2><div class="muted">Thêm nhiều ảnh cùng lúc; chọn ảnh đại diện ở phần thông tin phía trên.</div></div></div>
      <div class="field"><label>TẢI ẢNH BỔ SUNG (có thể chọn nhiều ảnh, mỗi ảnh tối đa 5 MB)</label><input type="file" name="anh_bo_sung[]" accept="image/jpeg,image/png,image/webp" multiple><small>Ảnh sẽ được lưu vào thư viện của riêng sản phẩm này.</small></div>
      <?php if ($gallery): ?><div class="gallery-grid">
        <?php foreach ($gallery as $img): ?><label class="gallery-card"><img src="../<?= e($img['duong_dan']) ?>" alt="<?= e($img['mo_ta_anh'] ?? '') ?>"><span><?= e($img['mo_ta_anh'] ?: 'Ảnh sản phẩm') ?></span><small><input type="checkbox" name="delete_gallery[]" value="<?= (int)$img['id'] ?>"> Xóa ảnh này</small></label><?php endforeach; ?>
      </div><?php else: ?><p class="muted">Chưa có ảnh bổ sung cho sản phẩm này.</p><?php endif; ?>
      <div class="form-actions"><button class="btn" type="submit">Lưu thông tin & hình ảnh</button></div>
    </div>
  </form>
  <?php if ($editing): ?><div class="product-next"><span>Biến thể là từng tổ hợp màu + size của sản phẩm; tại đây bạn nhập SKU và tồn kho cho từng tổ hợp.</span><a class="btn light" href="variants.php?product_id=<?= (int)$id ?>">Quản lý tồn kho & biến thể ↗</a></div><?php endif; ?>
</section>
<?php require __DIR__ . '/admin-footer.php'; ?>
