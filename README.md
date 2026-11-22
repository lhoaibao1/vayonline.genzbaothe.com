# Vay Online – landing so sánh khoản vay & thẻ tín dụng + CMS

Website giới thiệu khoản vay tiêu dùng và thẻ tín dụng (affiliate) kèm trang quản trị.
Viết bằng PHP 8.1+ thuần và SQLite: không cần composer, không cần MySQL, chạy được trên hosting cPanel thông thường.

## Tính năng

**Landing (khách xem)**
- Công cụ tính khoản vay: kéo số tiền, chọn kỳ hạn → ước tính trả hằng tháng, tự đưa gói phù hợp lên đầu.
- Bảng so sánh gói vay / thẻ (hạn mức, lãi suất, kỳ hạn hoặc phí thường niên, thời gian duyệt), lọc theo loại.
- Trang chi tiết từng gói `/uu-dai/{slug}`.
- Form đăng ký dạng popup (bottom sheet trên mobile): họ tên, SĐT, tỉnh/thành, thu nhập, số tiền.
  Sau khi gửi, khách được chuyển sang link affiliate của đối tác qua `/di/...` (có ký HMAC, ghi nhận lượt bấm).
- Nhiều landing page cho từng chiến dịch `/lp/{slug}`, mỗi trang có tiêu đề, bộ gói, SEO riêng.
- Ghi nhận UTM (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`), trang giới thiệu, lượt xem landing.
- Chống spam: honeypot, CSRF, giới hạn 15 đăng ký/giờ/IP, gộp đăng ký trùng (cùng SĐT + cùng gói trong 24h).
- Bắn sự kiện `lead_submit` (GTM dataLayer) và `Lead` (Facebook Pixel) khi đăng ký thành công.

**CMS `/admin`**
- Tổng quan: đăng ký hôm nay / 7 / 30 / 90 ngày, tỉ lệ chuyển đổi theo landing, theo gói, theo nguồn UTM, biểu đồ theo ngày.
- Khách đăng ký: lọc theo trạng thái, gói, loại, landing, nguồn, khoảng ngày; đổi trạng thái ngay trên bảng; thao tác hàng loạt; xuất CSV (mở được bằng Excel).
- Khách hàng: gộp theo số điện thoại, xem **toàn bộ các gói một khách đã đăng ký**, landing và UTM của từng lần, ghi chú chăm sóc.
- Gói vay & thẻ: thêm/sửa/ẩn/xoá, logo, link affiliate có biến `{sub_id}`, `{utm_source}`, `{utm_campaign}`, `{landing}`.
- Landing page: tạo nhiều trang, chọn loại hoặc từng gói hiển thị, chọn trang chủ, xem lượt xem / đăng ký / tỉ lệ.
- Tài khoản quản trị với 3 vai trò: Quản trị viên, Biên tập nội dung, Tư vấn/chăm sóc khách.
- Cài đặt: tên site, hotline, Zalo, chân trang, miễn trừ trách nhiệm, FAQ, mã theo dõi head/body.

Trạng thái đăng ký: Mới → Đã liên hệ → Đã nộp hồ sơ → Được duyệt / Từ chối / Rác.

## Chạy thử trên máy

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Mở http://127.0.0.1:8080 cho landing, http://127.0.0.1:8080/admin để tạo tài khoản quản trị đầu tiên.
Lần chạy đầu hệ thống tự tạo `storage/database.sqlite` kèm 5 gói mẫu và 2 landing mẫu.

## Triển khai lên hosting

1. Upload toàn bộ thư mục. Tốt nhất trỏ **document root vào `public/`**.
   Nếu hosting không cho đổi document root, file `.htaccess` ở thư mục gốc sẽ chuyển mọi request vào `public/` và chặn truy cập `app/`, `storage/`.
2. Cấp quyền ghi cho `storage/` và `public/uploads/`.
3. Truy cập `/admin` để tạo tài khoản quản trị (chỉ làm được khi chưa có tài khoản nào).
   Hoặc tạo/đặt lại mật khẩu bằng dòng lệnh:
   ```bash
   php bin/create-admin.php email@domain.com "mat-khau-moi" "Ho ten"
   ```
4. Vào **Gói vay & thẻ**, thay nội dung mẫu, logo và link affiliate thật.
5. Vào **Cài đặt website** dán mã GA/GTM/Pixel, hotline, Zalo.

Nginx: dùng `root /đường-dẫn/public;` và `try_files $uri /index.php?$query_string;`, chặn thực thi PHP trong `/uploads`.

Cấu hình tuỳ chọn: copy `config.example.php` thành `config.php`.

## Cấu trúc

```
app/        bootstrap, DB + migration, controller site/admin, views
public/     index.php (router), assets, uploads
storage/    database.sqlite (không public)
bin/        công cụ dòng lệnh
```

Sao lưu: chỉ cần copy `storage/database.sqlite` và `public/uploads/`.
