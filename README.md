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

**Pháp lý & dữ liệu cá nhân**
- Trang **Điều khoản sử dụng**, **Chính sách bảo mật**, **Thông báo xử lý dữ liệu cá nhân** (nội dung mẫu, sửa trong CMS mục *Trang chính sách*). Thông tin pháp nhân điền ở *Cài đặt* và tự chèn vào các trang; ô chưa điền hiện “[chưa cập nhật]”.
- Form đăng ký có ô đồng ý **không tick sẵn**, liên kết tới 3 chính sách. Mỗi đăng ký lưu thời điểm đồng ý và phiên bản chính sách khách đã đồng ý (sửa nội dung chính sách thì phiên bản tự tăng).
- Trang `/yeu-cau-du-lieu`: khách yêu cầu xem, sửa, xoá dữ liệu, rút lại đồng ý. CMS mục *Yêu cầu dữ liệu* để xử lý, ghi người xử lý.
- **Ẩn danh hoá** dữ liệu một khách (xoá tên, SĐT, email, IP trên hồ sơ và mọi đăng ký) nhưng giữ số liệu thống kê.
- **Banner đồng ý cookie**: mã theo dõi nhập trong CMS (GA, GTM, Facebook/TikTok Pixel) chỉ chạy sau khi khách đồng ý nhóm "Đo lường & quảng cáo". Khách đổi lựa chọn qua nút *Cài đặt cookie* ở chân trang.
- Nội dung chính sách là bản mẫu, cần luật sư rà soát trước khi chạy thật.

## Cài lên VPS bằng một lệnh

VPS Ubuntu 22.04/24.04 hoặc Debian 12, tối thiểu 1 vCPU, 1GB RAM (script tự tạo swap), 20GB ổ đĩa.
Trỏ bản ghi A của tên miền về IP VPS, rồi SSH vào VPS và chạy:

```bash
curl -fsSL https://raw.githubusercontent.com/lhoaibao1/vayonline.genzbaothe.com/main/deploy/install.sh | sudo bash -s -- --domain genzbaothe.com
```

Repo riêng tư: tạo GitHub token chỉ có quyền đọc nội dung repo (Fine-grained token → Contents: Read-only), rồi chạy:

```bash
curl -fsSL -H "Authorization: Bearer TOKEN" https://raw.githubusercontent.com/lhoaibao1/vayonline.genzbaothe.com/main/deploy/install.sh | sudo bash -s -- --domain genzbaothe.com --token TOKEN
```

Script tự làm hết:

1. Cài Docker, Git, tường lửa (chỉ mở SSH, 80, 443); tạo swap nếu RAM ít.
2. Tải code, tự sinh mật khẩu database và `APP_KEY` vào `/opt/genzbaothe/.env`.
3. Build và chạy Postgres, API Go, tracker Rust, landing Nuxt, CMS Vue, Caddy (HTTPS Let's Encrypt tự động).
4. **Tự cập nhật**: cứ 2 phút kiểm tra nhánh git; có commit mới thì tự tải, build và chạy lại. Build lỗi thì website giữ bản cũ và chờ commit sửa lỗi.
5. **Sao lưu** database và ảnh tải lên lúc 3 giờ sáng hằng ngày vào `/var/backups/genzbaothe`, giữ 14 bản.
6. Cài lệnh quản lý `genz`.

Tham số: `--domain` (bắt buộc), `--email` (nhận thông báo HTTPS), `--branch` (mặc định `main`), `--token` (repo riêng tư), `--interval` (chu kỳ kiểm tra cập nhật, mặc định `2min`).
Chạy lại lệnh cài bất kỳ lúc nào cũng an toàn: dữ liệu và mật khẩu được giữ nguyên.

```
genz status            Xem dịch vụ và bản code đang chạy
genz logs [dịch vụ]    Xem log (api, web, tracker, caddy, db)
genz update            Cập nhật ngay
genz update-log        Lịch sử tự cập nhật
genz backup            Sao lưu ngay
genz restore <file>    Khôi phục database
genz restart [dịch vụ] Khởi động lại
```

## Chạy bằng Docker (thủ công)

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
