-- Trang pháp lý (điều khoản, chính sách bảo mật, dữ liệu cá nhân) và yêu cầu của chủ thể dữ liệu

CREATE TABLE pages (
    slug       TEXT PRIMARY KEY,
    title      TEXT NOT NULL,
    content    TEXT NOT NULL DEFAULT '',
    version    INT NOT NULL DEFAULT 1,       -- tăng mỗi lần sửa nội dung, ghi vào từng đăng ký khi khách đồng ý
    sort_order INT NOT NULL DEFAULT 100,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Bằng chứng khách đã đồng ý: thời điểm + phiên bản chính sách lúc đồng ý
ALTER TABLE registrations ADD COLUMN consent_at TIMESTAMPTZ;
ALTER TABLE registrations ADD COLUMN consent_versions TEXT NOT NULL DEFAULT '';
ALTER TABLE registrations ADD COLUMN anonymized_at TIMESTAMPTZ;
ALTER TABLE customers ADD COLUMN anonymized_at TIMESTAMPTZ;

CREATE TABLE data_requests (
    id          BIGSERIAL PRIMARY KEY,
    type        TEXT NOT NULL CHECK (type IN ('access', 'correct', 'delete', 'withdraw', 'restrict', 'other')),
    full_name   TEXT NOT NULL,
    phone       TEXT NOT NULL,
    email       TEXT NOT NULL DEFAULT '',
    content     TEXT NOT NULL DEFAULT '',
    customer_id BIGINT REFERENCES customers(id) ON DELETE SET NULL,
    status      TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'processing', 'done', 'rejected')),
    note        TEXT NOT NULL DEFAULT '',
    ip          TEXT NOT NULL DEFAULT '',
    handled_by  BIGINT REFERENCES admins(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_data_requests ON data_requests (status, created_at DESC);
