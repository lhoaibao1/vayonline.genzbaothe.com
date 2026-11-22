<?php
// Tạo hoặc đặt lại mật khẩu tài khoản quản trị từ dòng lệnh:
//   php bin/create-admin.php email@domain.com "Mật khẩu" "Họ tên"
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

[$_, $email, $pass, $name] = $argv + [null, null, null, 'Quản trị viên'];
if (!$email || !$pass || strlen($pass) < 8) {
    fwrite(STDERR, "Cách dùng: php bin/create-admin.php email \"mat-khau-it-nhat-8-ky-tu\" \"Ho ten\"\n");
    exit(1);
}
$email = mb_strtolower($email);
$hash = password_hash($pass, PASSWORD_DEFAULT);
if ($a = row('SELECT id FROM admins WHERE email = ?', [$email])) {
    update('admins', (int)$a['id'], ['password_hash' => $hash, 'active' => 1, 'role' => 'admin']);
    echo "Đã đặt lại mật khẩu cho $email\n";
} else {
    insert('admins', ['name' => $name, 'email' => $email, 'password_hash' => $hash, 'role' => 'admin', 'active' => 1, 'created_at' => now()]);
    echo "Đã tạo tài khoản quản trị $email\n";
}
