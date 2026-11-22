# GenZbaothe.com

Landing giới thiệu **khoản vay tiêu dùng** và **thẻ tín dụng** (affiliate) kèm CMS quản lý khách đăng ký.
Hai module tách riêng: gói vay, gói thẻ, form đăng ký, danh sách khách và file xuất đều độc lập.

| Thư mục | Công nghệ | Việc |
|---|---|---|
| `web/` | Vue 3 / **Nuxt 3** (render ở server) | Landing: trang chủ, `/khoan-vay`, `/the-tin-dung`, `/lp/{slug}`, `/uu-dai/{slug}` |
| `admin/` | **Vue 3** + Vite (SPA) | CMS tại `/admin/` |
| `api/` | **Go** 1.24 (net/http, pgx) | API công khai + API quản trị, đăng nhập, phân quyền |
| `tracker/` | **Rust** (axum, sqlx) | Đếm lượt xem landing, ghi click, chuyển hướng `/go/{id}-{sig}` sang link đối tác |
| `deploy/` | Caddy | HTTPS tự động, chia đường dẫn cho các service |
| | **PostgreSQL** 16 | Dữ liệu |

```
Trình duyệt ──► Caddy ─┬─ /api/*, /uploads/* ──► api (Go) ─────┐
                       ├─ /go/*, /t/*        ──► tracker (Rust) ┼─► PostgreSQL
                       ├─ /admin/*           ──► file tĩnh CMS  │
                       └─ còn lại            ──► web (Nuxt) ────┘ (gọi API qua mạng nội bộ)
```

## Tính năng

**Landing**
- Công cụ tính khoản vay: kéo số tiền, chọn kỳ hạn, xem tiền trả mỗi tháng; gói vay phù hợp tự lên đầu.
- Khu **Khoản vay** và khu **Thẻ tín dụng** riêng, mỗi module có trang riêng.
- Form đăng ký khác nhau theo module:
  - Chung: họ tên, SĐT, năm sinh, tỉnh/thành, công việc, thu nhập.
  - Vay: số tiền, kỳ hạn, mục đích vay, hình thức nhận lương.
  - Thẻ: email, đã có thẻ tín dụng chưa, nhu cầu dùng thẻ.
- Sau khi gửi, khách được chuyển qua tracker Rust sang link đối tác (có `{sub_id}` để đối soát).
- Ghi nguồn UTM, trang giới thiệu, landing; chống spam (bẫy bot, giới hạn theo IP, gộp đăng ký trùng 24h).
- Gửi sự kiện `lead_submit` (GTM) và `Lead` (Facebook Pixel). Mã theo dõi nhập trong CMS.

**CMS**
- Tổng quan tách 2 module: chờ xử lý, hôm nay, 7/30/90 ngày, tỉ lệ sang đối tác, được duyệt; biểu đồ theo ngày; theo landing; theo nguồn.
- **Đăng ký vay** / **Đăng ký mở thẻ**: lọc theo trạng thái, gói, landing, nguồn, ngày; đổi trạng thái ngay trên bảng; thao tác hàng loạt; xuất CSV với cột riêng từng module.
- Chi tiết từng đăng ký: toàn bộ thông tin khách điền lúc đăng ký, nguồn truy cập, ghi chú xử lý, các gói khác khách đã đăng ký.
- Khách hàng gộp theo SĐT, xem mọi lần đăng ký vay và thẻ.
- **Gói vay** / **Gói thẻ**: thêm, sửa, ẩn, xoá, logo, link affiliate.
- Landing page, tài khoản quản trị (3 vai trò), cài đặt website.

## Chạy bằng Docker

```bash
cp .env.example .env
```

Sửa `.env`: `SITE_DOMAIN`, `POSTGRES_PASSWORD`, `APP_KEY` (tạo bằng `openssl rand -hex 32`). Sau đó:

```bash
docker compose up -d --build
```

Mở `https://{SITE_DOMAIN}/admin/` để tạo tài khoản quản trị đầu tiên, rồi vào **Gói vay** và **Gói thẻ** thay nội dung mẫu và link đối tác.

## Chạy từng phần khi phát triển

Cần Go 1.24, Rust 1.8x, Node 22, PostgreSQL 16.

```bash
cd api && APP_KEY=$(openssl rand -hex 32) COOKIE_SECURE=0 DATABASE_URL=postgres://genz:genz@localhost:5432/genz?sslmode=disable go run .
```

```bash
cd tracker && APP_KEY=<cùng khoá> DATABASE_URL=postgres://genz:genz@localhost:5432/genz cargo run
```

```bash
cd web && npm install && NUXT_PROXY_BACKENDS=true npm run dev
```

```bash
cd admin && npm install && npm run dev
```

Landing ở http://localhost:3000, CMS ở http://localhost:5173/admin/. Đặt `SEED_DEMO=1` cho API để có dữ liệu minh hoạ (tài khoản `demo@genzbaothe.com` / `demo12345`).

## Ghi chú kỹ thuật

- Bảng được tạo tự động khi API khởi động (`api/migrations/*.sql`).
- Link `/go/{id}-{sig}`: API Go ký bằng HMAC-SHA256 với `APP_KEY`, tracker Rust kiểm tra lại bằng cùng khoá. Hai service phải dùng chung `APP_KEY`.
- API quản trị dùng cookie phiên `HttpOnly`; mọi yêu cầu thay đổi dữ liệu phải có header `X-Requested-With` để chặn CSRF.
- Sao lưu: database PostgreSQL và volume `uploads`.

## Bản demo tĩnh

Thư mục `demo/` dựng bản xem thử không cần server: landing và CMS chạy với API giả lập (`demo/shim.js`) và dữ liệu mẫu ghi từ API thật (`demo/fixtures.json`).

```bash
python3 demo/record.py http://127.0.0.1:8080 demo@genzbaothe.com demo12345
```

```bash
(cd web && DEMO=1 npx nuxi generate) && (cd admin && DEMO=1 npx vite build) && python3 demo/build.py /tmp/genz-demo
```
