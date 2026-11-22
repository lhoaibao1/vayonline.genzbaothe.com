<?php
declare(strict_types=1);

function admin_view(string $name, array $data = []): void
{
    view('admin/' . $name, $data, 'admin/layout');
}

/* ---------- Đăng nhập ---------- */

function admin_login(): void
{
    if (!(int)val('SELECT COUNT(*) FROM admins')) redirect('/admin/setup');
    if (current_admin()) redirect('/admin');
    view('admin/login', ['error' => null]);
}

function admin_login_post(): void
{
    csrf_check();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if (login_throttled($email)) {
        view('admin/login', ['error' => 'Đăng nhập sai quá nhiều lần. Hãy thử lại sau 15 phút.']);
        return;
    }
    $a = row('SELECT * FROM admins WHERE email = ? AND active = 1', [$email]);
    if (!$a || !password_verify($pass, $a['password_hash'])) {
        login_record_fail();
        view('admin/login', ['error' => 'Email hoặc mật khẩu không đúng.']);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$a['id'];
    q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $a['id']]);
    $next = (string)($_GET['next'] ?? '');
    redirect(str_starts_with($next, '/admin') ? $next : '/admin');
}

function admin_setup(): void
{
    if ((int)val('SELECT COUNT(*) FROM admins')) redirect('/admin/login');
    view('admin/setup', ['errors' => []]);
}

function admin_setup_post(): void
{
    csrf_check();
    if ((int)val('SELECT COUNT(*) FROM admins')) redirect('/admin/login');
    $errors = validate_admin_input($_POST, null);
    if ($errors) {
        view('admin/setup', ['errors' => $errors]);
        return;
    }
    $id = insert('admins', [
        'name' => trim($_POST['name']), 'email' => mb_strtolower(trim($_POST['email'])),
        'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'role' => 'admin', 'active' => 1, 'created_at' => now(),
    ]);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    flash('success', 'Đã tạo tài khoản quản trị. Hãy kiểm tra các gói vay mẫu và thay link affiliate.');
    redirect('/admin');
}

function admin_logout(): void
{
    csrf_check();
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
    redirect('/admin/login');
}

/* ---------- Tổng quan ---------- */

function admin_dashboard(): void
{
    require_admin('leads.view');
    $range = in_array($_GET['range'] ?? '', ['7', '30', '90'], true) ? (int)$_GET['range'] : 30;
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($range - 1) . ' days'));
    $fromDay = substr($from, 0, 10);

    $stats = [
        'today'     => (int)val('SELECT COUNT(*) FROM registrations WHERE created_at >= ?', [date('Y-m-d 00:00:00')]),
        'regs'      => (int)val('SELECT COUNT(*) FROM registrations WHERE created_at >= ?', [$from]),
        'customers' => (int)val('SELECT COUNT(DISTINCT customer_id) FROM registrations WHERE created_at >= ?', [$from]),
        'clicked'   => (int)val('SELECT COUNT(*) FROM registrations WHERE created_at >= ? AND clicked_out_at IS NOT NULL', [$from]),
        'views'     => (int)val('SELECT COALESCE(SUM(views),0) FROM landing_views WHERE day >= ?', [$fromDay]),
        'new'       => (int)val("SELECT COUNT(*) FROM registrations WHERE status = 'new'"),
    ];

    $daily = [];
    for ($i = $range - 1; $i >= 0; $i--) $daily[date('Y-m-d', strtotime("-$i days"))] = ['regs' => 0, 'views' => 0];
    foreach (rows("SELECT substr(created_at,1,10) d, COUNT(*) c FROM registrations WHERE created_at >= ? GROUP BY d", [$from]) as $r) {
        if (isset($daily[$r['d']])) $daily[$r['d']]['regs'] = (int)$r['c'];
    }
    foreach (rows('SELECT day, SUM(views) v FROM landing_views WHERE day >= ? GROUP BY day', [$fromDay]) as $r) {
        if (isset($daily[$r['day']])) $daily[$r['day']]['views'] = (int)$r['v'];
    }

    $byOffer = rows("SELECT o.id, o.name, o.type, COUNT(r.id) regs, SUM(CASE WHEN r.clicked_out_at IS NOT NULL THEN 1 ELSE 0 END) clicked,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) approved
        FROM offers o LEFT JOIN registrations r ON r.offer_id = o.id AND r.created_at >= ?
        GROUP BY o.id ORDER BY regs DESC, o.sort_order", [$from]);

    $byLanding = rows("SELECT l.id, l.name, l.slug,
        (SELECT COALESCE(SUM(views),0) FROM landing_views v WHERE v.landing_id = l.id AND v.day >= ?) views,
        (SELECT COUNT(*) FROM registrations r WHERE r.landing_id = l.id AND r.created_at >= ?) regs
        FROM landings l ORDER BY regs DESC", [$fromDay, $from]);

    $bySource = rows("SELECT COALESCE(NULLIF(utm_source,''), '(trực tiếp)') src, COUNT(*) c FROM registrations WHERE created_at >= ? GROUP BY src ORDER BY c DESC LIMIT 8", [$from]);

    $recent = rows('SELECT r.*, c.full_name, c.phone FROM registrations r JOIN customers c ON c.id = r.customer_id ORDER BY r.id DESC LIMIT 8');

    admin_view('dashboard', compact('range', 'stats', 'daily', 'byOffer', 'byLanding', 'bySource', 'recent') + ['title' => 'Tổng quan']);
}

/* ---------- Đăng ký (lead) ---------- */

function leads_filter(): array
{
    $f = [
        'q'       => trim((string)($_GET['q'] ?? '')),
        'status'  => (string)($_GET['status'] ?? ''),
        'offer'   => (int)($_GET['offer'] ?? 0),
        'landing' => (int)($_GET['landing'] ?? 0),
        'type'    => (string)($_GET['type'] ?? ''),
        'source'  => trim((string)($_GET['source'] ?? '')),
        'from'    => (string)($_GET['from'] ?? ''),
        'to'      => (string)($_GET['to'] ?? ''),
    ];
    $where = ['1=1'];
    $p = [];
    if ($f['q'] !== '') {
        $where[] = '(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    if (isset(status_labels()[$f['status']])) { $where[] = 'r.status = ?'; $p[] = $f['status']; }
    if ($f['offer']) { $where[] = 'r.offer_id = ?'; $p[] = $f['offer']; }
    if ($f['landing']) { $where[] = 'r.landing_id = ?'; $p[] = $f['landing']; }
    if (isset(offer_types()[$f['type']])) { $where[] = 'o.type = ?'; $p[] = $f['type']; }
    if ($f['source'] !== '') { $where[] = 'r.utm_source = ?'; $p[] = $f['source']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'r.created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'r.created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    return [$f, implode(' AND ', $where), $p];
}

const LEADS_SELECT = 'SELECT r.*, c.full_name, c.phone, c.email, c.province, c.income, o.type offer_type, o.name current_offer_name, l.name landing_name
    FROM registrations r
    JOIN customers c ON c.id = r.customer_id
    LEFT JOIN offers o ON o.id = r.offer_id
    LEFT JOIN landings l ON l.id = r.landing_id';

function admin_leads(): void
{
    require_admin('leads.view');
    [$f, $where, $p] = leads_filter();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 30;
    $total = (int)val("SELECT COUNT(*) FROM registrations r JOIN customers c ON c.id = r.customer_id LEFT JOIN offers o ON o.id = r.offer_id WHERE $where", $p);
    $leads = rows(LEADS_SELECT . " WHERE $where ORDER BY r.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    $counts = [];
    foreach (rows('SELECT status, COUNT(*) c FROM registrations GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
    admin_view('leads', [
        'title' => 'Khách đăng ký', 'f' => $f, 'leads' => $leads, 'total' => $total, 'page' => $page, 'per' => $per, 'counts' => $counts,
        'offers' => rows('SELECT id, name FROM offers ORDER BY sort_order'),
        'landings' => rows('SELECT id, name FROM landings ORDER BY id'),
        'sources' => array_column(rows("SELECT DISTINCT utm_source FROM registrations WHERE utm_source IS NOT NULL AND utm_source != '' ORDER BY 1"), 'utm_source'),
    ]);
}

function admin_leads_export(): void
{
    require_admin('leads.export');
    [, $where, $p] = leads_filter();
    $st = q(LEADS_SELECT . " WHERE $where ORDER BY r.id DESC", $p);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dang-ky-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
    fputcsv($out, ['Mã', 'Thời gian', 'Họ tên', 'SĐT', 'Email', 'Tỉnh/TP', 'Thu nhập', 'Gói đăng ký', 'Loại', 'Số tiền', 'Kỳ hạn (tháng)', 'Landing', 'Trạng thái', 'Đã chuyển sang đối tác', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'Ghi chú'], ',', '"', '');
    $st_l = status_labels();
    $inc = income_options();
    while ($r = $st->fetch()) {
        fputcsv($out, [
            'R' . $r['id'], $r['created_at'], $r['full_name'], "'" . $r['phone'], $r['email'], $r['province'], $inc[$r['income']] ?? '',
            $r['current_offer_name'] ?? $r['offer_name'], offer_types()[$r['offer_type']] ?? '', $r['amount'], $r['term'], $r['landing_name'],
            $st_l[$r['status']] ?? $r['status'], $r['clicked_out_at'] ? 'Có' : 'Chưa',
            $r['utm_source'], $r['utm_medium'], $r['utm_campaign'], $r['utm_content'], $r['note'],
        ], ',', '"', '');
    }
    fclose($out);
}

function admin_leads_status(): void
{
    require_admin('leads.edit');
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if (isset(status_labels()[$status])) {
        $data = ['status' => $status, 'updated_at' => now()];
        if (isset($_POST['note'])) $data['note'] = mb_substr(trim((string)$_POST['note']), 0, 1000);
        update('registrations', $id, $data);
    }
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') json_out(['ok' => true]);
    flash('success', 'Đã cập nhật trạng thái.');
    redirect($_POST['back'] ?? '/admin/leads');
}

function admin_leads_bulk(): void
{
    require_admin('leads.edit');
    csrf_check();
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    $action = (string)($_POST['action'] ?? '');
    if ($ids) {
        $in = implode(',', $ids);
        if ($action === 'delete') {
            require_admin('leads.delete');
            q("DELETE FROM registrations WHERE id IN ($in)");
            flash('success', 'Đã xoá ' . count($ids) . ' đăng ký.');
        } elseif (isset(status_labels()[$action])) {
            q("UPDATE registrations SET status = ?, updated_at = ? WHERE id IN ($in)", [$action, now()]);
            flash('success', 'Đã chuyển ' . count($ids) . ' đăng ký sang "' . status_labels()[$action] . '".');
        }
    }
    $back = (string)($_POST['back'] ?? '');
    redirect(str_starts_with($back, '/admin') ? $back : '/admin/leads');
}

/* ---------- Khách hàng ---------- */

function admin_customers(): void
{
    require_admin('leads.view');
    $qtext = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 30;
    $where = '1=1';
    $p = [];
    if ($qtext !== '') {
        $where = '(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $p = array_fill(0, 3, '%' . $qtext . '%');
    }
    $total = (int)val("SELECT COUNT(*) FROM customers c WHERE $where", $p);
    $customers = rows("SELECT c.*, COUNT(r.id) regs, MAX(r.created_at) last_reg,
        GROUP_CONCAT(COALESCE(o.name, r.offer_name), ' | ') offer_list
        FROM customers c LEFT JOIN registrations r ON r.customer_id = c.id LEFT JOIN offers o ON o.id = r.offer_id
        WHERE $where GROUP BY c.id ORDER BY last_reg DESC, c.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    admin_view('customers', ['title' => 'Khách hàng', 'customers' => $customers, 'q' => $qtext, 'total' => $total, 'page' => $page, 'per' => $per]);
}

function admin_customer(string $id): void
{
    require_admin('leads.view');
    $c = row('SELECT * FROM customers WHERE id = ?', [(int)$id]);
    if (!$c) not_found();
    $regs = rows('SELECT r.*, o.name current_offer_name, o.type offer_type, o.slug offer_slug, l.name landing_name, l.slug landing_slug
        FROM registrations r LEFT JOIN offers o ON o.id = r.offer_id LEFT JOIN landings l ON l.id = r.landing_id
        WHERE r.customer_id = ? ORDER BY r.id DESC', [$c['id']]);
    admin_view('customer', ['title' => $c['full_name'], 'c' => $c, 'regs' => $regs]);
}

function admin_customer_save(string $id): void
{
    require_admin('leads.edit');
    csrf_check();
    $c = row('SELECT * FROM customers WHERE id = ?', [(int)$id]);
    if (!$c) not_found();
    $phone = normalize_phone((string)($_POST['phone'] ?? ''));
    if (!$phone) {
        flash('error', 'Số điện thoại không hợp lệ.');
        redirect('/admin/customers/' . $c['id']);
    }
    $dup = row('SELECT id FROM customers WHERE phone = ? AND id != ?', [$phone, $c['id']]);
    if ($dup) {
        flash('error', 'Số điện thoại này đã thuộc về khách hàng #' . $dup['id'] . '.');
        redirect('/admin/customers/' . $c['id']);
    }
    update('customers', (int)$c['id'], [
        'full_name' => trim((string)$_POST['full_name']) ?: $c['full_name'],
        'phone' => $phone,
        'email' => trim((string)($_POST['email'] ?? '')),
        'province' => trim((string)($_POST['province'] ?? '')),
        'income' => (string)($_POST['income'] ?? ''),
        'note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000),
        'updated_at' => now(),
    ]);
    flash('success', 'Đã lưu thông tin khách hàng.');
    redirect('/admin/customers/' . $c['id']);
}

function admin_customer_delete(string $id): void
{
    require_admin('leads.delete');
    csrf_check();
    q('DELETE FROM customers WHERE id = ?', [(int)$id]);
    flash('success', 'Đã xoá khách hàng và toàn bộ đăng ký của khách.');
    redirect('/admin/customers');
}

/* ---------- Gói vay / thẻ ---------- */

function admin_offers(): void
{
    require_admin('offers');
    $offers = rows("SELECT o.*, (SELECT COUNT(*) FROM registrations r WHERE r.offer_id = o.id) regs,
        (SELECT COUNT(*) FROM registrations r WHERE r.offer_id = o.id AND r.created_at >= ?) regs30
        FROM offers o ORDER BY o.type, o.sort_order, o.id", [date('Y-m-d', strtotime('-29 days'))]);
    admin_view('offers', ['title' => 'Gói vay & thẻ', 'offers' => $offers]);
}

function admin_offer_form(?string $id = null): void
{
    require_admin('offers');
    $o = $id ? row('SELECT * FROM offers WHERE id = ?', [(int)$id]) : null;
    if ($id && !$o) not_found();
    $o ??= ['id' => 0, 'type' => 'loan', 'slug' => '', 'name' => '', 'partner' => '', 'logo' => '', 'badge' => '', 'amount_min' => 1000000, 'amount_max' => 50000000,
        'rate_text' => '', 'rate_min' => 0, 'term_min' => 3, 'term_max' => 36, 'age_text' => '', 'approval_text' => '', 'annual_fee' => '',
        'highlights' => '', 'requirements' => '', 'description' => '', 'affiliate_url' => '', 'sort_order' => 100, 'active' => 1];
    admin_view('offer_form', ['title' => $o['id'] ? 'Sửa gói: ' . $o['name'] : 'Thêm gói mới', 'o' => $o, 'errors' => []]);
}

function admin_offer_save(): void
{
    require_admin('offers');
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $existing = $id ? row('SELECT * FROM offers WHERE id = ?', [$id]) : null;
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    $money = fn($k) => (int)preg_replace('/\D/', '', (string)($_POST[$k] ?? '0'));
    $data = [
        'type' => isset(offer_types()[$P('type')]) ? $P('type') : 'loan',
        'name' => $P('name'),
        'slug' => slugify($P('slug') ?: $P('name')),
        'partner' => $P('partner'),
        'badge' => $P('badge'),
        'amount_min' => $money('amount_min'),
        'amount_max' => $money('amount_max'),
        'rate_text' => $P('rate_text'),
        'rate_min' => (float)str_replace(',', '.', $P('rate_min')),
        'term_min' => (int)$P('term_min'),
        'term_max' => (int)$P('term_max'),
        'age_text' => $P('age_text'),
        'approval_text' => $P('approval_text'),
        'annual_fee' => $P('annual_fee'),
        'highlights' => $P('highlights'),
        'requirements' => $P('requirements'),
        'description' => $P('description'),
        'affiliate_url' => $P('affiliate_url'),
        'sort_order' => (int)$P('sort_order'),
        'active' => isset($_POST['active']) ? 1 : 0,
        'updated_at' => now(),
    ];
    $errors = [];
    if ($data['name'] === '') $errors['name'] = 'Nhập tên gói.';
    if ($data['affiliate_url'] !== '' && !preg_match('#^https?://#i', $data['affiliate_url'])) $errors['affiliate_url'] = 'Link phải bắt đầu bằng http:// hoặc https://';
    if ($data['amount_max'] && $data['amount_min'] > $data['amount_max']) $errors['amount_max'] = 'Hạn mức tối đa phải lớn hơn tối thiểu.';
    if (row('SELECT id FROM offers WHERE slug = ? AND id != ?', [$data['slug'], $id])) $errors['slug'] = 'Đường dẫn này đã được dùng cho gói khác.';
    try {
        $logo = handle_upload('logo_file');
        if ($logo) $data['logo'] = $logo;
        elseif (isset($_POST['remove_logo'])) $data['logo'] = '';
    } catch (RuntimeException $ex) {
        $errors['logo'] = $ex->getMessage();
    }
    if ($errors) {
        $o = array_merge($existing ?? [], $data, ['id' => $id, 'logo' => $existing['logo'] ?? '']);
        admin_view('offer_form', ['title' => $id ? 'Sửa gói' : 'Thêm gói mới', 'o' => $o, 'errors' => $errors]);
        return;
    }
    if ($existing) {
        update('offers', $id, $data);
    } else {
        $data['created_at'] = now();
        $id = insert('offers', $data);
    }
    flash('success', 'Đã lưu gói "' . $data['name'] . '".');
    redirect('/admin/offers');
}

function admin_offer_toggle(string $id): void
{
    require_admin('offers');
    csrf_check();
    q('UPDATE offers SET active = 1 - active, updated_at = ? WHERE id = ?', [now(), (int)$id]);
    redirect('/admin/offers');
}

function admin_offer_delete(string $id): void
{
    require_admin('offers');
    csrf_check();
    q('DELETE FROM offers WHERE id = ?', [(int)$id]);
    flash('success', 'Đã xoá gói. Các đăng ký cũ vẫn giữ tên gói tại thời điểm đăng ký.');
    redirect('/admin/offers');
}

/* ---------- Landing page ---------- */

function admin_landings(): void
{
    require_admin('landings');
    $from = date('Y-m-d', strtotime('-29 days'));
    $landings = rows('SELECT l.*,
        (SELECT COALESCE(SUM(views),0) FROM landing_views v WHERE v.landing_id = l.id AND v.day >= ?) views30,
        (SELECT COUNT(*) FROM registrations r WHERE r.landing_id = l.id AND r.created_at >= ?) regs30,
        (SELECT COUNT(*) FROM registrations r WHERE r.landing_id = l.id) regs
        FROM landings l ORDER BY l.is_default DESC, l.id', [$from, $from . ' 00:00:00']);
    admin_view('landings', ['title' => 'Landing page', 'landings' => $landings]);
}

function admin_landing_form(?string $id = null): void
{
    require_admin('landings');
    $l = $id ? row('SELECT * FROM landings WHERE id = ?', [(int)$id]) : null;
    if ($id && !$l) not_found();
    $l ??= ['id' => 0, 'slug' => '', 'name' => '', 'headline' => '', 'subheadline' => '', 'offer_type' => 'all', 'offer_ids' => '', 'show_calculator' => 1,
        'seo_title' => '', 'seo_description' => '', 'is_default' => 0, 'active' => 1];
    admin_view('landing_form', [
        'title' => $l['id'] ? 'Sửa landing: ' . $l['name'] : 'Tạo landing mới', 'l' => $l, 'errors' => [],
        'offers' => rows('SELECT id, name, type, active FROM offers ORDER BY type, sort_order'),
    ]);
}

function admin_landing_save(): void
{
    require_admin('landings');
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    $data = [
        'name' => $P('name'),
        'slug' => slugify($P('slug') ?: $P('name')),
        'headline' => $P('headline'),
        'subheadline' => $P('subheadline'),
        'offer_type' => in_array($P('offer_type'), ['all', 'loan', 'card'], true) ? $P('offer_type') : 'all',
        'offer_ids' => implode(',', array_filter(array_map('intval', (array)($_POST['offer_ids'] ?? [])))),
        'show_calculator' => isset($_POST['show_calculator']) ? 1 : 0,
        'seo_title' => $P('seo_title'),
        'seo_description' => $P('seo_description'),
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
        'active' => isset($_POST['active']) ? 1 : 0,
        'updated_at' => now(),
    ];
    $errors = [];
    if ($data['name'] === '') $errors['name'] = 'Nhập tên landing để phân biệt trong báo cáo.';
    if ($data['headline'] === '') $errors['headline'] = 'Nhập tiêu đề chính.';
    if (row('SELECT id FROM landings WHERE slug = ? AND id != ?', [$data['slug'], $id])) $errors['slug'] = 'Đường dẫn này đã được dùng.';
    if ($errors) {
        admin_view('landing_form', ['title' => 'Landing page', 'l' => $data + ['id' => $id], 'errors' => $errors,
            'offers' => rows('SELECT id, name, type, active FROM offers ORDER BY type, sort_order')]);
        return;
    }
    if ($data['is_default']) q('UPDATE landings SET is_default = 0');
    if ($id) {
        update('landings', $id, $data);
    } else {
        $data['created_at'] = now();
        $id = insert('landings', $data);
    }
    flash('success', 'Đã lưu landing "' . $data['name'] . '".');
    redirect('/admin/landings');
}

function admin_landing_delete(string $id): void
{
    require_admin('landings');
    csrf_check();
    $l = row('SELECT * FROM landings WHERE id = ?', [(int)$id]);
    if ($l && $l['is_default']) {
        flash('error', 'Không xoá được landing đang làm trang chủ. Hãy đặt landing khác làm trang chủ trước.');
    } else {
        q('DELETE FROM landings WHERE id = ?', [(int)$id]);
        flash('success', 'Đã xoá landing.');
    }
    redirect('/admin/landings');
}

/* ---------- Tài khoản quản trị ---------- */

function validate_admin_input(array $in, ?int $id, bool $passwordRequired = true): array
{
    $errors = [];
    if (mb_strlen(trim((string)($in['name'] ?? ''))) < 2) $errors['name'] = 'Nhập họ tên.';
    $email = mb_strtolower(trim((string)($in['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email chưa đúng định dạng.';
    elseif (row('SELECT id FROM admins WHERE email = ? AND id != ?', [$email, $id ?? 0])) $errors['email'] = 'Email này đã có tài khoản.';
    $pw = (string)($in['password'] ?? '');
    if (($passwordRequired || $pw !== '') && mb_strlen($pw) < 8) $errors['password'] = 'Mật khẩu cần ít nhất 8 ký tự.';
    return $errors;
}

function admin_users(): void
{
    require_admin('admins');
    admin_view('users', ['title' => 'Tài khoản quản trị', 'users' => rows('SELECT * FROM admins ORDER BY id')]);
}

function admin_user_form(?string $id = null): void
{
    require_admin('admins');
    $u = $id ? row('SELECT * FROM admins WHERE id = ?', [(int)$id]) : null;
    if ($id && !$u) not_found();
    $u ??= ['id' => 0, 'name' => '', 'email' => '', 'role' => 'sales', 'active' => 1];
    admin_view('user_form', ['title' => $u['id'] ? 'Sửa tài khoản' : 'Thêm tài khoản', 'u' => $u, 'errors' => []]);
}

function admin_user_save(): void
{
    $me = require_admin('admins');
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $errors = validate_admin_input($_POST, $id ?: null, !$id);
    $role = isset(ROLES[$_POST['role'] ?? '']) ? $_POST['role'] : 'sales';
    $active = isset($_POST['active']) ? 1 : 0;
    if ($id === (int)$me['id'] && ($role !== 'admin' || !$active)) $errors['role'] = 'Bạn không thể tự hạ quyền hoặc khoá tài khoản của chính mình.';
    if ($errors) {
        admin_view('user_form', ['title' => 'Tài khoản', 'u' => ['id' => $id, 'name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'role' => $role, 'active' => $active], 'errors' => $errors]);
        return;
    }
    $data = ['name' => trim($_POST['name']), 'email' => mb_strtolower(trim($_POST['email'])), 'role' => $role, 'active' => $active];
    if (($_POST['password'] ?? '') !== '') $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    if ($id) update('admins', $id, $data);
    else insert('admins', $data + ['created_at' => now()]);
    flash('success', 'Đã lưu tài khoản ' . $data['email'] . '.');
    redirect('/admin/users');
}

function admin_user_delete(string $id): void
{
    $me = require_admin('admins');
    csrf_check();
    if ((int)$id === (int)$me['id']) {
        flash('error', 'Bạn không thể xoá tài khoản đang đăng nhập.');
    } else {
        q('DELETE FROM admins WHERE id = ?', [(int)$id]);
        flash('success', 'Đã xoá tài khoản.');
    }
    redirect('/admin/users');
}

function admin_profile(): void
{
    $me = require_admin();
    admin_view('profile', ['title' => 'Tài khoản của tôi', 'u' => $me, 'errors' => []]);
}

function admin_profile_save(): void
{
    $me = require_admin();
    csrf_check();
    $errors = validate_admin_input($_POST + ['email' => $me['email']], (int)$me['id'], false);
    if (!password_verify((string)($_POST['current_password'] ?? ''), $me['password_hash'])) $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
    if ($errors) {
        admin_view('profile', ['title' => 'Tài khoản của tôi', 'u' => $me, 'errors' => $errors]);
        return;
    }
    $data = ['name' => trim($_POST['name'])];
    if (($_POST['password'] ?? '') !== '') $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    update('admins', (int)$me['id'], $data);
    flash('success', 'Đã cập nhật tài khoản.');
    redirect('/admin/profile');
}

/* ---------- Cài đặt ---------- */

const SETTING_KEYS = ['site_name', 'site_tagline', 'hotline', 'zalo', 'contact_email', 'company_info', 'disclaimer', 'faq', 'head_scripts', 'body_scripts'];

function admin_settings(): void
{
    require_admin('settings');
    $s = [];
    foreach (SETTING_KEYS as $k) $s[$k] = setting($k);
    admin_view('settings', ['title' => 'Cài đặt website', 's' => $s]);
}

function admin_settings_save(): void
{
    require_admin('settings');
    csrf_check();
    foreach (SETTING_KEYS as $k) {
        if (isset($_POST[$k])) set_setting($k, trim((string)$_POST[$k]));
    }
    flash('success', 'Đã lưu cài đặt.');
    redirect('/admin/settings');
}
