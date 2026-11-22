-- GenZbaothe.com: cấu trúc dữ liệu ban đầu

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE admins (
    id            BIGSERIAL PRIMARY KEY,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'sales' CHECK (role IN ('admin', 'editor', 'sales')),
    active        BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE sessions (
    token_hash TEXT PRIMARY KEY,
    admin_id   BIGINT NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE login_attempts (
    ip         TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_login_attempts ON login_attempts (ip, created_at);

-- type: 'loan' = khoản vay, 'card' = thẻ tín dụng. Hai module tách riêng.
CREATE TABLE offers (
    id            BIGSERIAL PRIMARY KEY,
    type          TEXT NOT NULL CHECK (type IN ('loan', 'card')),
    slug          TEXT NOT NULL UNIQUE,
    name          TEXT NOT NULL,
    partner       TEXT NOT NULL DEFAULT '',
    logo          TEXT NOT NULL DEFAULT '',
    badge         TEXT NOT NULL DEFAULT '',
    amount_min    BIGINT NOT NULL DEFAULT 0,
    amount_max    BIGINT NOT NULL DEFAULT 0,
    rate_text     TEXT NOT NULL DEFAULT '',
    rate_min      NUMERIC(6,2) NOT NULL DEFAULT 0,  -- %/năm, chỉ dùng cho khoản vay
    term_min      INT NOT NULL DEFAULT 0,
    term_max      INT NOT NULL DEFAULT 0,
    age_text      TEXT NOT NULL DEFAULT '',
    approval_text TEXT NOT NULL DEFAULT '',
    annual_fee    TEXT NOT NULL DEFAULT '',          -- chỉ dùng cho thẻ
    highlights    TEXT NOT NULL DEFAULT '',
    requirements  TEXT NOT NULL DEFAULT '',
    description   TEXT NOT NULL DEFAULT '',
    affiliate_url TEXT NOT NULL DEFAULT '',
    sort_order    INT NOT NULL DEFAULT 100,
    active        BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_offers_type ON offers (type, active, sort_order);

CREATE TABLE landings (
    id              BIGSERIAL PRIMARY KEY,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    headline        TEXT NOT NULL DEFAULT '',
    subheadline     TEXT NOT NULL DEFAULT '',
    offer_type      TEXT NOT NULL DEFAULT 'all' CHECK (offer_type IN ('all', 'loan', 'card')),
    offer_ids       BIGINT[] NOT NULL DEFAULT '{}',
    show_calculator BOOLEAN NOT NULL DEFAULT TRUE,
    seo_title       TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    is_default      BOOLEAN NOT NULL DEFAULT FALSE,
    active          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Rust tracker ghi bảng này
CREATE TABLE landing_views (
    landing_id BIGINT NOT NULL REFERENCES landings(id) ON DELETE CASCADE,
    day        DATE NOT NULL,
    views      INT NOT NULL DEFAULT 0,
    PRIMARY KEY (landing_id, day)
);

CREATE TABLE customers (
    id         BIGSERIAL PRIMARY KEY,
    full_name  TEXT NOT NULL,
    phone      TEXT NOT NULL UNIQUE,
    email      TEXT NOT NULL DEFAULT '',
    birth_year INT,
    province   TEXT NOT NULL DEFAULT '',
    occupation TEXT NOT NULL DEFAULT '',
    income     TEXT NOT NULL DEFAULT '',
    note       TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Mỗi lần đăng ký lưu nguyên thông tin khách điền lúc đó
CREATE TABLE registrations (
    id             BIGSERIAL PRIMARY KEY,
    customer_id    BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    offer_id       BIGINT REFERENCES offers(id) ON DELETE SET NULL,
    offer_name     TEXT NOT NULL,
    offer_type     TEXT NOT NULL CHECK (offer_type IN ('loan', 'card')),
    landing_id     BIGINT REFERENCES landings(id) ON DELETE SET NULL,
    full_name      TEXT NOT NULL,
    phone          TEXT NOT NULL,
    email          TEXT NOT NULL DEFAULT '',
    birth_year     INT NOT NULL,
    province       TEXT NOT NULL,
    occupation     TEXT NOT NULL,
    income         TEXT NOT NULL,
    -- khoản vay
    amount         BIGINT,
    term           INT,
    purpose        TEXT NOT NULL DEFAULT '',
    salary_method  TEXT NOT NULL DEFAULT '',
    -- thẻ tín dụng
    has_card       TEXT NOT NULL DEFAULT '',
    card_need      TEXT NOT NULL DEFAULT '',
    status         TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'contacted', 'submitted', 'approved', 'rejected', 'spam')),
    note           TEXT NOT NULL DEFAULT '',
    utm_source     TEXT NOT NULL DEFAULT '',
    utm_medium     TEXT NOT NULL DEFAULT '',
    utm_campaign   TEXT NOT NULL DEFAULT '',
    utm_content    TEXT NOT NULL DEFAULT '',
    referrer       TEXT NOT NULL DEFAULT '',
    ip             TEXT NOT NULL DEFAULT '',
    user_agent     TEXT NOT NULL DEFAULT '',
    clicked_out_at TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_reg_type_created ON registrations (offer_type, created_at DESC);
CREATE INDEX idx_reg_status ON registrations (offer_type, status);
CREATE INDEX idx_reg_customer ON registrations (customer_id);
CREATE INDEX idx_reg_offer ON registrations (offer_id);
CREATE INDEX idx_reg_landing ON registrations (landing_id);
CREATE INDEX idx_reg_ip ON registrations (ip, created_at);
