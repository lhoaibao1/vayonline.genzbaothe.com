<?php
// Sao chép thành config.php để ghi đè cấu hình mặc định.
return [
    // Đường dẫn file SQLite – nên đặt ngoài thư mục public
    'db_path' => __DIR__ . '/storage/database.sqlite',
    // Chuỗi bí mật dùng ký link chuyển hướng. Để trống thì hệ thống tự sinh và lưu trong DB.
    'app_key' => '',
    'debug'   => false,
];
