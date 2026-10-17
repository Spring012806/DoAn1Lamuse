
<?php
$host = 'localhost';
$dbname = 'lamuse'; // Phải trùng tên database trong phpMyAdmin
$username = 'root';
$password = ''; // Thay bằng mật khẩu MySQL nếu máy bạn có đặt

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
}  catch (PDOException $e) {
    exit('Lỗi kết nối database: ' . $e->getMessage());

}
?>

