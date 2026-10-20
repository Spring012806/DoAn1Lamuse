<?php
require_once __DIR__.'/bootstrap.php';require_admin();$pageTitle='Tồn kho & biến thể';$active='variants';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)($_POST['id']??0);try{if(($_POST['action']??'')==='delete'){$pdo->prepare("DELETE FROM bien_the_sanpham WHERE id=?")->execute([$id]);flash('success','Đã xóa biến thể.');}else{$product=(int)($_POST['san_pham_id']??0);$color=(int)($_POST['mau_sac_id']??0);$size=(int)($_POST['kich_thuoc_id']??0);
// Tự sinh SKU từ mã sản phẩm + mã màu + size

$product = (int)($_POST['san_pham_id'] ?? 0);
$color   = (int)($_POST['mau_sac_id'] ?? 0);
$size    = (int)($_POST['kich_thuoc_id'] ?? 0);
$price   = trim((string)($_POST['gia_ban'] ?? ''));
$stock   = filter_var(
    $_POST['so_luong_ton'] ?? null,
    FILTER_VALIDATE_INT
);
$status  = filter_var(
    $_POST['trang_thai'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($product < 1 || $color < 1 || $size < 1) {
    throw new RuntimeException('Vui lòng chọn sản phẩm, màu sắc và size.');
}

if ($stock === false || $stock < 0) {
    throw new RuntimeException('Tồn kho phải là số nguyên không âm.');
}

if (!in_array($status, [0, 1], true)) {
    throw new RuntimeException('Trạng thái biến thể không hợp lệ.');
}

if (
    $price !== ''
    && (!is_numeric($price) || (float)$price < 0)
) {
    throw new RuntimeException('Giá riêng phải là số không âm.');
}

// Xác nhận sản phẩm, màu và size tồn tại, đang hoạt động.
$check = $pdo->prepare(
    'SELECT id FROM sanpham WHERE id = ?'
);
$check->execute([$product]);

if (!$check->fetchColumn()) {
    throw new RuntimeException('Sản phẩm không tồn tại.');
}

$check = $pdo->prepare(
    'SELECT id FROM mausac WHERE id = ? AND trang_thai = 1'
);
$check->execute([$color]);

if (!$check->fetchColumn()) {
    throw new RuntimeException('Màu sắc không tồn tại hoặc đã ngừng hoạt động.');
}

$check = $pdo->prepare(
    'SELECT id FROM kichthuoc WHERE id = ? AND trang_thai = 1'
);
$check->execute([$size]);

if (!$check->fetchColumn()) {
    throw new RuntimeException('Size không tồn tại hoặc đã ngừng hoạt động.');
}


$check = $pdo->prepare(
    'SELECT id
     FROM bien_the_sanpham
     WHERE san_pham_id = ?
       AND mau_sac_id = ?
       AND kich_thuoc_id = ?
       AND id <> ?
     LIMIT 1'
);

$check->execute([$product, $color, $size, $id]);

if ($check->fetchColumn()) {
    throw new RuntimeException(
        'Biến thể này đã tồn tại. Vui lòng chọn tổ hợp màu và size khác.'
    );
}


// Nếu sửa biến thể, giữ SKU cũ khi tổ hợp không đổi.
$sku = '';

if ($id > 0) {
    $oldStmt = $pdo->prepare(
        'SELECT san_pham_id, mau_sac_id, kich_thuoc_id, sku
         FROM bien_the_sanpham
         WHERE id = ?'
    );
    $oldStmt->execute([$id]);
    $oldVariant = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldVariant) {
        throw new RuntimeException('Không tìm thấy biến thể cần sửa.');
    }

    if (
        (int)$oldVariant['san_pham_id'] === $product
        && (int)$oldVariant['mau_sac_id'] === $color
        && (int)$oldVariant['kich_thuoc_id'] === $size
    ) {
        $sku = (string)$oldVariant['sku'];
    }
}

// Nếu là biến thể mới hoặc đã đổi tổ hợp, tạo SKU mới.
if ($sku === '') {
    $stmt = $pdo->prepare(
        'SELECT ma_san_pham FROM sanpham WHERE id = ?'
    );
    $stmt->execute([$product]);
    $productCode = trim((string)$stmt->fetchColumn());

    $stmt = $pdo->prepare(
        'SELECT ten_mau FROM mausac WHERE id = ?'
    );
    $stmt->execute([$color]);
    $colorName = trim((string)$stmt->fetchColumn());

    $stmt = $pdo->prepare(
        'SELECT ten_size FROM kichthuoc WHERE id = ?'
    );
    $stmt->execute([$size]);
    $sizeName = trim((string)$stmt->fetchColumn());

    if ($productCode === '' || $colorName === '' || $sizeName === '') {
        throw new RuntimeException('Không thể tạo SKU từ dữ liệu đã chọn.');
    }

    $normalize = static function (string $value): string {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = preg_replace('/[^A-Z0-9]/', '', $value);
        return $value !== '' ? $value : 'XX';
    };

    $colorCode = substr($normalize($colorName), 0, 2);
    $sizeCode = $normalize($sizeName);

    $baseSku = $productCode . '-' . $colorCode . '-' . $sizeCode;
    $sku = $baseSku;

    // Tránh xung đột SKU với biến thể khác.
    $suffix = 2;
    $checkSku = $pdo->prepare(
        'SELECT id FROM bien_the_sanpham WHERE sku = ? AND id <> ? LIMIT 1'
    );

    do {
        $checkSku->execute([$sku, $id]);
        if (!$checkSku->fetchColumn()) {
            break;
        }

        $sku = $baseSku . '-' . $suffix;
        $suffix++;
    } while (true);

    if (strlen($sku) > 60) {
        throw new RuntimeException('SKU vượt quá độ dài cho phép.');
    }
}

$vals=[$product,$color,$size,$sku,$price===''?null:(float)$price,$stock,$status];
if($id){$vals[]=$id;$pdo->prepare("UPDATE bien_the_sanpham SET san_pham_id=?,mau_sac_id=?,kich_thuoc_id=?,sku=?,gia_ban=?,so_luong_ton=?,trang_thai=? WHERE id=?")->execute($vals);}else $pdo->prepare("INSERT INTO bien_the_sanpham (san_pham_id,mau_sac_id,kich_thuoc_id,sku,gia_ban,so_luong_ton,trang_thai) VALUES (?,?,?,?,?,?,?)")->execute($vals);flash('success','Đã lưu biến thể và tồn kho.');}}catch(Throwable $e){flash('error',$e instanceof PDOException?'Không thể lưu: SKU hoặc tổ hợp sản phẩm/màu/size đã tồn tại, hoặc dữ liệu không hợp lệ.':$e->getMessage());}redirect('variants.php'.(!empty($_GET['product_id'])?'?product_id='.(int)$_GET['product_id']:''));}
$products=$pdo->query("SELECT id,ma_san_pham,ten_san_pham FROM sanpham ORDER BY ten_san_pham")->fetchAll();$colors=$pdo->query("SELECT id,ten_mau FROM mausac WHERE trang_thai=1 ORDER BY ten_mau")->fetchAll();$sizes=$pdo->query("SELECT id,ten_size FROM kichthuoc WHERE trang_thai=1 ORDER BY thu_tu")->fetchAll();
$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare("SELECT * FROM bien_the_sanpham WHERE id=?");$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$productFilter=(int)($_GET['product_id']??0);$sql="SELECT b.*,p.ten_san_pham,p.ma_san_pham,m.ten_mau,k.ten_size FROM bien_the_sanpham b JOIN sanpham p ON p.id=b.san_pham_id LEFT JOIN mausac m ON m.id=b.mau_sac_id LEFT JOIN kichthuoc k ON k.id=b.kich_thuoc_id";$params=[];if($productFilter){$sql.=" WHERE b.san_pham_id=?";$params[]=$productFilter;}$sql.=" ORDER BY p.ten_san_pham,m.ten_mau,k.thu_tu";$s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll();require __DIR__.'/admin-header.php';?>
<section class="panel"><div class="panel-head"><div><h2><?=$edit?'Sửa biến thể':'Tạo biến thể mới'?></h2><div class="muted">Ví dụ: Váy hoa nhí + Trắng + S là một biến thể riêng; nhập SKU và số lượng tồn cho đúng tổ hợp đó. Muốn thêm màu/size mới, vào mục Màu sắc hoặc Kích thước ở menu bên trái trước.</div></div></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><div class="form-grid">
<div class="field"><label>SẢN PHẨM *</label><select name="san_pham_id" required><option value="">Chọn sản phẩm</option><?php foreach($products as $p):?><option value="<?=$p['id']?>" <?=((string)($edit['san_pham_id']??$productFilter)===(string)$p['id'])?'selected':''?>><?=e($p['ma_san_pham'].' — '.$p['ten_san_pham'])?></option><?php endforeach;?></select></div>
<div class="field">
    <label>SKU</label>
    <input
        type="text"
        value="<?=e($edit['sku'] ?? 'Tự động tạo khi lưu')?>"
        readonly
        placeholder="Tự động tạo từ sản phẩm, màu và size"
    >
    <small class="muted">
        SKU được tạo tự động khi lưu biến thể.
    </small>
</div>
<div class="field"><label>MÀU SẮC *</label><select name="mau_sac_id" required><option value="">Chọn màu</option><?php foreach($colors as $c):?><option value="<?=$c['id']?>" <?=((string)($edit['mau_sac_id']??'')===(string)$c['id'])?'selected':''?>><?=e($c['ten_mau'])?></option><?php endforeach;?></select></div>
<div class="field"><label>KÍCH THƯỚC *</label><select name="kich_thuoc_id" required><option value="">Chọn size</option><?php foreach($sizes as $z):?><option value="<?=$z['id']?>" <?=((string)($edit['kich_thuoc_id']??'')===(string)$z['id'])?'selected':''?>><?=e($z['ten_size'])?></option><?php endforeach;?></select></div>
<div class="field"><label>GIÁ RIÊNG (VNĐ)</label><input type="number" min="0" step="1000" name="gia_ban" value="<?=e($edit['gia_ban']??'')?>" placeholder="Để trống dùng giá sản phẩm"></div>
<div class="field"><label>SỐ LƯỢNG TỒN *</label><input type="number" min="0" name="so_luong_ton" required value="<?=e($edit['so_luong_ton']??0)?>"></div>
<div class="field"><label>TRẠNG THÁI</label><select name="trang_thai"><option value="1" <?=((int)($edit['trang_thai']??1)===1)?'selected':''?>>Đang hoạt động</option><option value="0" <?=((int)($edit['trang_thai']??1)===0)?'selected':''?>>Ngừng hoạt động</option></select></div></div><div class="form-actions"><button class="btn">Lưu biến thể</button><a class="btn light" href="variants.php">Làm mới</a></div></form></section>
<section class="panel"><div class="panel-head"><div><h2>Danh sách biến thể</h2><div class="muted">Tồn kho được quản lý theo từng SKU.</div></div><form class="searchbar" method="get"><select name="product_id"><option value="0">Tất cả sản phẩm</option><?php foreach($products as $p):?><option value="<?=$p['id']?>" <?=$productFilter===(int)$p['id']?'selected':''?>><?=e($p['ma_san_pham'])?></option><?php endforeach;?></select><button class="btn light">Lọc</button></form></div><div class="table-wrap"><table><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Màu</th><th>Size</th><th>Giá riêng</th><th>Tồn kho</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="8" class="empty">Chưa có biến thể.</td></tr><?php endif;?><?php foreach($rows as $r):?><tr><td><strong><?=e($r['sku'])?></strong></td><td><?=e($r['ten_san_pham'])?></td><td><?=e($r['ten_mau']??'—')?></td><td><?=e($r['ten_size']??'—')?></td><td><?=$r['gia_ban']!==null?number_format((float)$r['gia_ban'],0,',','.').' ₫':'Theo giá SP'?></td><td><span class="badge <?=((int)$r['so_luong_ton']===0)?'off':''?>"><?=(int)$r['so_luong_ton']?></span></td><td><?=((int)$r['trang_thai']===1)?'Hoạt động':'Tắt'?></td><td><div class="actions"><a class="btn small light" href="?edit=<?=$r['id']?>">Sửa</a><form class="inline" method="post" onsubmit="return confirm('Xóa biến thể này?')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="action" value="delete"><button class="btn small danger">Xóa</button></form></div></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/admin-footer.php';?>
