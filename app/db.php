<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = cfg('db_path');
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = "INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($key === '__reset') { $cache = null; return null; }
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT key, value FROM settings') as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, ?string $value): void
{
    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, (string)$value]);
    setting('__reset');
}

function migrate(PDO $pdo): void
{
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= 1) return;

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    );
    CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'sales',
        active INTEGER NOT NULL DEFAULT 1,
        last_login_at TEXT,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS offers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL DEFAULT 'loan',
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        partner TEXT,
        logo TEXT,
        badge TEXT,
        amount_min INTEGER DEFAULT 0,
        amount_max INTEGER DEFAULT 0,
        rate_text TEXT,
        rate_min REAL DEFAULT 0,
        term_min INTEGER DEFAULT 0,
        term_max INTEGER DEFAULT 0,
        age_text TEXT,
        approval_text TEXT,
        annual_fee TEXT,
        highlights TEXT,
        requirements TEXT,
        description TEXT,
        affiliate_url TEXT,
        sort_order INTEGER DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS landings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        headline TEXT,
        subheadline TEXT,
        offer_type TEXT NOT NULL DEFAULT 'all',
        offer_ids TEXT,
        show_calculator INTEGER NOT NULL DEFAULT 1,
        seo_title TEXT,
        seo_description TEXT,
        is_default INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS landing_views (
        landing_id INTEGER NOT NULL REFERENCES landings(id) ON DELETE CASCADE,
        day TEXT NOT NULL,
        views INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (landing_id, day)
    );
    CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        phone TEXT NOT NULL UNIQUE,
        email TEXT,
        province TEXT,
        income TEXT,
        birth_year INTEGER,
        note TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS registrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
        offer_id INTEGER REFERENCES offers(id) ON DELETE SET NULL,
        offer_name TEXT,
        landing_id INTEGER REFERENCES landings(id) ON DELETE SET NULL,
        amount INTEGER,
        term INTEGER,
        status TEXT NOT NULL DEFAULT 'new',
        note TEXT,
        utm_source TEXT,
        utm_medium TEXT,
        utm_campaign TEXT,
        utm_content TEXT,
        referrer TEXT,
        ip TEXT,
        user_agent TEXT,
        clicked_out_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_reg_created ON registrations(created_at);
    CREATE INDEX IF NOT EXISTS idx_reg_offer ON registrations(offer_id);
    CREATE INDEX IF NOT EXISTS idx_reg_landing ON registrations(landing_id);
    CREATE INDEX IF NOT EXISTS idx_reg_customer ON registrations(customer_id);
    CREATE INDEX IF NOT EXISTS idx_reg_ip ON registrations(ip, created_at);
    SQL);

    seed($pdo);
    $pdo->exec('PRAGMA user_version = 1');
}

function seed(PDO $pdo): void
{
    $now = now();
    $settings = [
        'site_name'     => 'Vay Online',
        'site_tagline'  => 'So sánh khoản vay và thẻ tín dụng, đăng ký trong 2 phút',
        'hotline'       => '',
        'zalo'          => '',
        'contact_email' => '',
        'company_info'  => 'Website giới thiệu sản phẩm tài chính từ các tổ chức tín dụng được cấp phép. Chúng tôi không trực tiếp cho vay và không thu bất kỳ khoản phí nào từ khách hàng.',
        'disclaimer'    => 'Lãi suất, hạn mức và thời gian duyệt chỉ mang tính tham khảo, có thể thay đổi theo chính sách của từng đối tác và hồ sơ của bạn. Quyết định phê duyệt thuộc về tổ chức tín dụng. Hãy đọc kỹ hợp đồng trước khi ký.',
        'head_scripts'  => '',
        'body_scripts'  => '',
        'faq'           => "Tôi có mất phí khi đăng ký không?|Không. Việc đăng ký qua website là miễn phí. Bạn chỉ trả lãi và phí theo hợp đồng ký trực tiếp với đối tác.\nCần giấy tờ gì để vay?|Thường chỉ cần CCCD gắn chip còn hiệu lực. Một số khoản vay hạn mức cao sẽ cần thêm sao kê lương hoặc hợp đồng lao động.\nBao lâu thì có kết quả?|Tùy đối tác: khoản vay online thường có kết quả trong 15 phút đến 24 giờ, thẻ tín dụng từ 1 đến 7 ngày làm việc.\nNợ xấu có vay được không?|Các khoản vay đều yêu cầu thẩm định lịch sử tín dụng. Nếu bạn đang có nợ nhóm 3 trở lên, khả năng được duyệt rất thấp.\nThông tin của tôi có được bảo mật không?|Thông tin chỉ dùng để chuyển đến đối tác bạn đã chọn và để tư vấn viên liên hệ hỗ trợ. Chúng tôi không bán dữ liệu cho bên thứ ba.",
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    $offers = [
        ['loan', 'vay-tieu-dung-cccd', 'Vay tiêu dùng chỉ cần CCCD', 'Đối tác mẫu A', 'Duyệt nhanh', 5_000_000, 70_000_000, 'Từ 1,6%/tháng', 19.2, 6, 36, '20 – 60 tuổi', 'Trong 24 giờ',
            '', "Chỉ cần CCCD gắn chip\nGiải ngân qua tài khoản ngân hàng\nTrả góp cố định hằng tháng", "Công dân Việt Nam 20 – 60 tuổi\nThu nhập từ 3 triệu/tháng\nKhông có nợ xấu nhóm 3 trở lên"],
        ['loan', 'vay-online-24h', 'Vay online giải ngân trong ngày', 'Đối tác mẫu B', 'Khoản nhỏ', 1_000_000, 20_000_000, 'Từ 2,2%/tháng', 26.4, 3, 12, '18 – 60 tuổi', '15 – 30 phút',
            '', "Hồ sơ online 100%\nKhoản vay đầu tiên có thể miễn lãi 7 ngày\nTất toán sớm linh hoạt", "Từ 18 tuổi, có CCCD\nCó tài khoản ngân hàng chính chủ\nCó số điện thoại đứng tên"],
        ['loan', 'vay-tin-chap-theo-luong', 'Vay tín chấp theo lương', 'Ngân hàng mẫu C', 'Hạn mức cao', 30_000_000, 500_000_000, 'Từ 1,1%/tháng', 13.2, 12, 60, '22 – 58 tuổi', '2 – 3 ngày',
            '', "Hạn mức đến 15 lần lương\nKhông cần tài sản đảm bảo\nLãi suất tính trên dư nợ giảm dần", "Nhận lương qua chuyển khoản từ 7 triệu/tháng\nHợp đồng lao động từ 1 năm\nĐang làm việc tại nơi hiện tại tối thiểu 6 tháng"],
        ['card', 'the-hoan-tien', 'Thẻ tín dụng hoàn tiền mua sắm', 'Ngân hàng mẫu D', 'Hoàn tiền 10%', 10_000_000, 100_000_000, 'Miễn lãi đến 55 ngày', 0, 0, 0, '18 – 65 tuổi', '3 – 5 ngày',
            'Miễn phí năm đầu', "Hoàn tiền 10% khi mua sắm online\nTrả góp 0% tại đối tác liên kết\nMở thẻ online, nhận thẻ phi vật lý ngay"],
        ['card', 'the-du-lich', 'Thẻ tín dụng du lịch', 'Ngân hàng mẫu E', 'Tích dặm', 20_000_000, 300_000_000, 'Miễn lãi đến 45 ngày', 0, 0, 0, '21 – 65 tuổi', '5 – 7 ngày',
            '899.000đ/năm', "Tích dặm bay cho mọi chi tiêu\nPhòng chờ sân bay miễn phí\nBảo hiểm du lịch toàn cầu"],
    ];
    $st = $pdo->prepare('INSERT INTO offers (type, slug, name, partner, badge, amount_min, amount_max, rate_text, rate_min, term_min, term_max, age_text, approval_text, annual_fee, highlights, requirements, description, affiliate_url, sort_order, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
    foreach ($offers as $i => $o) {
        [$type, $slug, $name, $partner, $badge, $min, $max, $rateText, $rate, $tmin, $tmax, $age, $approval, $fee, $hl] = $o;
        $req = $o[15] ?? "Công dân Việt Nam từ 18 tuổi\nCó thu nhập ổn định\nCCCD gắn chip còn hiệu lực";
        $st->execute([$type, $slug, $name, $partner, $badge, $min, $max, $rateText, $rate, $tmin, $tmax, $age, $approval, $fee, $hl, $req,
            'Đây là sản phẩm mẫu. Hãy sửa nội dung, link affiliate và logo trong trang quản trị.',
            'https://example.com/?sub_id={sub_id}&utm_source={utm_source}', ($i + 1) * 10, $now, $now]);
    }

    $pdo->prepare('INSERT INTO landings (slug, name, headline, subheadline, offer_type, offer_ids, show_calculator, seo_title, seo_description, is_default, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)')
        ->execute(['trang-chu', 'Trang chủ', 'Cần tiền gấp? *Chọn gói, duyệt liền.*', 'Kéo thanh chọn số tiền, xem ngay mỗi tháng trả bao nhiêu. Đăng ký 2 phút, đối tác gọi lại trong ngày.', 'all', '', 1, 'Vay Online – So sánh khoản vay và thẻ tín dụng', 'So sánh lãi suất, hạn mức các khoản vay tiêu dùng và thẻ tín dụng. Đăng ký online trong 2 phút.', 1, $now, $now]);
    $pdo->prepare('INSERT INTO landings (slug, name, headline, subheadline, offer_type, offer_ids, show_calculator, seo_title, seo_description, is_default, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)')
        ->execute(['mo-the-tin-dung', 'Chiến dịch thẻ tín dụng', 'Mở thẻ online, *miễn lãi 55 ngày*', 'So sánh ưu đãi hoàn tiền, phí thường niên và chọn thẻ hợp thói quen chi tiêu của bạn.', 'card', '', 0, 'Mở thẻ tín dụng online', 'So sánh thẻ tín dụng hoàn tiền, du lịch. Mở thẻ online.', 0, $now, $now]);
}
