<?php
declare(strict_types=1);

function landing_offers(array $landing): array
{
    $sql = 'SELECT * FROM offers WHERE active = 1';
    $params = [];
    if ($landing['offer_type'] !== 'all') {
        $sql .= ' AND type = ?';
        $params[] = $landing['offer_type'];
    }
    $ids = array_filter(array_map('intval', explode(',', (string)$landing['offer_ids'])));
    if ($ids) {
        $sql .= ' AND id IN (' . implode(',', $ids) . ')';
    }
    return rows($sql . ' ORDER BY sort_order, id', $params);
}

function track_view(int $landingId): void
{
    // Không tính lượt xem lặp lại trong cùng phiên
    $k = 'viewed_' . $landingId . '_' . date('Ymd');
    if (!empty($_SESSION[$k])) return;
    $_SESSION[$k] = 1;
    q('INSERT INTO landing_views (landing_id, day, views) VALUES (?, ?, 1) ON CONFLICT(landing_id, day) DO UPDATE SET views = views + 1', [$landingId, date('Y-m-d')]);
}

function remember_utm(): void
{
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $k) {
        if (!empty($_GET[$k])) $_SESSION['utm'][$k] = mb_substr((string)$_GET[$k], 0, 120);
    }
    if (empty($_SESSION['referrer']) && !empty($_SERVER['HTTP_REFERER'])) {
        $_SESSION['referrer'] = mb_substr($_SERVER['HTTP_REFERER'], 0, 255);
    }
}

function render_landing(array $landing): void
{
    remember_utm();
    track_view((int)$landing['id']);
    $_SESSION['landing_id'] = (int)$landing['id'];
    view('site/landing', [
        'landing' => $landing,
        'offers'  => landing_offers($landing),
        'faq'     => array_map(fn($l) => explode('|', $l, 2) + [1 => ''], lines(setting('faq'))),
    ], 'site/layout');
}

function site_home(): void
{
    $landing = row('SELECT * FROM landings WHERE is_default = 1 AND active = 1')
        ?? row('SELECT * FROM landings WHERE active = 1 ORDER BY id LIMIT 1');
    if (!$landing) not_found();
    render_landing($landing);
}

function site_landing(string $slug): void
{
    $landing = row('SELECT * FROM landings WHERE slug = ? AND active = 1', [$slug]);
    if (!$landing) not_found();
    render_landing($landing);
}

function site_offer(string $slug): void
{
    $offer = row('SELECT * FROM offers WHERE slug = ? AND active = 1', [$slug]);
    if (!$offer) not_found();
    remember_utm();
    $related = rows('SELECT * FROM offers WHERE active = 1 AND type = ? AND id != ? ORDER BY sort_order LIMIT 3', [$offer['type'], $offer['id']]);
    view('site/offer', ['offer' => $offer, 'related' => $related], 'site/layout');
}

function site_register(): void
{
    csrf_check();

    // Bẫy bot: trường ẩn phải để trống
    if (!empty($_POST['website'])) json_out(['ok' => true, 'redirect' => null]);

    $errors = [];
    $name = trim((string)($_POST['full_name'] ?? ''));
    $phone = normalize_phone((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $province = trim((string)($_POST['province'] ?? ''));
    $income = (string)($_POST['income'] ?? '');
    $amount = (int)preg_replace('/\D/', '', (string)($_POST['amount'] ?? '0'));
    $term = (int)($_POST['term'] ?? 0);
    $offer = row('SELECT * FROM offers WHERE id = ? AND active = 1', [(int)($_POST['offer_id'] ?? 0)]);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors['full_name'] = 'Nhập họ tên như trên CCCD.';
    if (!$phone) $errors['phone'] = 'Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email chưa đúng định dạng.';
    if ($province !== '' && !in_array($province, provinces(), true)) $errors['province'] = 'Chọn tỉnh/thành trong danh sách.';
    if ($income !== '' && !isset(income_options()[$income])) $income = '';
    if (!$offer) $errors['offer_id'] = 'Gói này không còn nhận đăng ký. Hãy chọn gói khác.';
    if (empty($_POST['consent'])) $errors['consent'] = 'Bạn cần đồng ý để đối tác liên hệ tư vấn.';

    if ($errors) json_out(['ok' => false, 'errors' => $errors], 422);

    $recent = (int)val("SELECT COUNT(*) FROM registrations WHERE ip = ? AND created_at > ?", [client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    if ($recent >= 15) json_out(['ok' => false, 'message' => 'Bạn đã gửi quá nhiều đăng ký. Hãy thử lại sau 1 giờ.'], 429);

    $now = now();
    $customer = row('SELECT * FROM customers WHERE phone = ?', [$phone]);
    if ($customer) {
        update('customers', (int)$customer['id'], [
            'full_name'  => $name,
            'email'      => $email ?: $customer['email'],
            'province'   => $province ?: $customer['province'],
            'income'     => $income ?: $customer['income'],
            'updated_at' => $now,
        ]);
        $customerId = (int)$customer['id'];
    } else {
        $customerId = insert('customers', [
            'full_name' => $name, 'phone' => $phone, 'email' => $email, 'province' => $province,
            'income' => $income, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $landingId = (int)($_POST['landing_id'] ?? 0) ?: (int)($_SESSION['landing_id'] ?? 0);
    $landing = $landingId ? row('SELECT * FROM landings WHERE id = ?', [$landingId]) : null;

    // Cùng SĐT đăng ký lại cùng gói trong 24h thì dùng lại bản ghi cũ
    $reg = row('SELECT * FROM registrations WHERE customer_id = ? AND offer_id = ? AND created_at > ? ORDER BY id DESC LIMIT 1',
        [$customerId, $offer['id'], date('Y-m-d H:i:s', time() - 86400)]);
    if (!$reg) {
        $utm = $_SESSION['utm'] ?? [];
        $regId = insert('registrations', [
            'customer_id'  => $customerId,
            'offer_id'     => $offer['id'],
            'offer_name'   => $offer['name'],
            'landing_id'   => $landing['id'] ?? null,
            'amount'       => $amount ?: null,
            'term'         => $term ?: null,
            'status'       => 'new',
            'utm_source'   => $utm['utm_source'] ?? null,
            'utm_medium'   => $utm['utm_medium'] ?? null,
            'utm_campaign' => $utm['utm_campaign'] ?? null,
            'utm_content'  => $utm['utm_content'] ?? null,
            'referrer'     => $_SESSION['referrer'] ?? null,
            'ip'           => client_ip(),
            'user_agent'   => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
        $reg = row('SELECT * FROM registrations WHERE id = ?', [$regId]);
    }

    $go = trim((string)$offer['affiliate_url']) !== '' ? url('di/' . $reg['id'] . '-' . sign('go' . $reg['id'])) : null;
    json_out(['ok' => true, 'redirect' => $go, 'offer' => $offer['name']]);
}

function site_go(string $id, string $sig): void
{
    if (!hash_equals(sign('go' . $id), $sig)) not_found();
    $reg = row('SELECT * FROM registrations WHERE id = ?', [(int)$id]);
    $offer = $reg ? row('SELECT * FROM offers WHERE id = ?', [$reg['offer_id']]) : null;
    if (!$reg || !$offer || !$offer['affiliate_url']) redirect('/');
    if (!$reg['clicked_out_at']) {
        q('UPDATE registrations SET clicked_out_at = ? WHERE id = ?', [now(), $reg['id']]);
    }
    $landing = $reg['landing_id'] ? row('SELECT * FROM landings WHERE id = ?', [$reg['landing_id']]) : null;
    $target = affiliate_link($offer, $reg, $landing);
    if (!preg_match('#^https?://#i', $target)) redirect('/');
    redirect($target);
}
