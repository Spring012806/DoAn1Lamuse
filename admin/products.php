<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$pageTitle='Sản phẩm'; $active='products';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();
 $id=(int)($_POST['id']??0);
 try {
  if (($_POST['action']??'')==='delete') {
   $q=$pdo->prepare("SELECT anh_dai_dien FROM sanpham WHERE id=?");$q->execute([$id]);$old=$q->fetchColumn();
   $pdo->prepare("DELETE FROM sanpham WHERE id=?")->execute([$id]);
   delete_local_image(is_string($old)?$old:null);
   flash('success','Đã xóa sản phẩm và dữ liệu liên quan theo ràng buộc CSDL.');
  } elseif (($_POST['action']??'')==='status') {
   $status=(string)($_POST['trang_thai']??'an');
   if (!in_array($status,['dang_ban','an','ngung_kinh_doanh'],true)) throw new RuntimeException('Trạng thái không hợp lệ.');
   $pdo->prepare("UPDATE sanpham SET trang_thai=? WHERE id=?")->execute([$status,$id]); flash('success','Đã cập nhật trạng thái.');
  }
 } catch(Throwable $e) { flash('error','Không thể thực hiện: '.$e->getMessage()); }
 redirect('products.php');
}
$search=trim((string)($_GET['q']??''));
$sql="SELECT p.*,d.ten_danh_muc FROM sanpham p LEFT JOIN danhmuc d ON d.id=p.danh_muc_id";
$params=[];
if($search!==''){$sql.=" WHERE p.ten_san_pham LIKE ? OR p.ma_san_pham LIKE ?";$params=['%'.$search.'%','%'.$search.'%'];}
$sql.=" ORDER BY p.id DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
require __DIR__.'/admin-header.php';
?>
<section class="panel"><div class="panel-head"><div><h2>Danh sách sản phẩm</h2><div class="muted">Quản lý thông tin, giá bán và trạng thái hiển thị.</div></div><a class="btn" href="product-form.php">＋ Thêm sản phẩm</a></div>
<form class="searchbar" method="get"><input name="q" placeholder="Tìm tên hoặc mã sản phẩm…" value="<?=e($search)?>"><button class="btn light">Tìm kiếm</button><?php if($search!==''):?><a class="btn light" href="products.php">Xóa lọc</a><?php endif;?></form>
<div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>Ảnh</th><th>Mã / Tên sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="6" class="empty">Chưa có sản phẩm phù hợp.</td></tr><?php endif;?>
<?php foreach($rows as $p):?><tr><td><?php if($p['anh_dai_dien']):?><img class="thumb" src="../<?=e($p['anh_dai_dien'])?>" alt=""><?php else:?>—<?php endif;?></td><td><strong><?=e($p['ma_san_pham'])?></strong><br><?=e($p['ten_san_pham'])?></td><td><?=e($p['ten_danh_muc']??'—')?></td><td><?=number_format((float)($p['gia_khuyen_mai']??$p['gia_niem_yet']),0,',','.')?> ₫</td><td><span class="badge <?= $p['trang_thai']==='dang_ban'?'':'off' ?>"><?=e($p['trang_thai'])?></span></td><td><div class="actions"><a class="btn small light" href="product-form.php?id=<?=(int)$p['id']?>">Sửa</a><a class="btn small light" href="variants.php?product_id=<?=(int)$p['id']?>">Tồn kho & biến thể</a><form class="inline" method="post" onsubmit="return confirm('Xóa sản phẩm này? Nếu có dữ liệu liên quan, thao tác có thể bị từ chối bởi khóa ngoại.')"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><input type="hidden" name="action" value="delete"><button class="btn small danger">Xóa</button></form></div></td></tr><?php endforeach;?>
</tbody></table></div></section>
<?php require __DIR__.'/admin-footer.php';?>
