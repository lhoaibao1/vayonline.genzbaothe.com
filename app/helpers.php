<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_key(): string
{
    $k = cfg('app_key');
    if ($k) return $k;
    $k = setting('_app_key');
    if (!$k) {
        $k = bin2hex(random_bytes(32));
        set_setting('_app_key', $k);
    }
    return $k;
}

function base_url(string $path = ''): string
{
    $scheme = ((($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function url(string $path = '', array $query = []): string
{
    $u = '/' . ltrim($path, '/');
    $query = array_filter($query, fn($v) => $v !== '' && $v !== null);
    return $query ? $u . '?' . http_build_query($query) : $u;
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function view(string $name, array $data = [], ?string $layout = null): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $name . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require APP . '/views/' . $layout . '.php';
    } else {
        echo $content;
    }
}

function not_found(): never
{
    http_response_code(404);
    view('site/404', [], null);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Hãy tải lại trang và thử lại.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function slugify(string $s): string
{
    $map = [
        'à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ' => 'a', 'è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ' => 'e',
        'ì|í|ị|ỉ|ĩ' => 'i', 'ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ' => 'o',
        'ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ' => 'u', 'ỳ|ý|ỵ|ỷ|ỹ' => 'y', 'đ' => 'd',
    ];
    $s = mb_strtolower(trim($s));
    foreach ($map as $from => $to) {
        $s = preg_replace('/' . $from . '/u', $to, $s);
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'item';
}

function money_vnd(int|float|null $n, bool $short = true): string
{
    $n = (float)($n ?? 0);
    if ($short) {
        if ($n >= 1_000_000_000) return rtrim(rtrim(number_format($n / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' tỷ';
        if ($n >= 1_000_000) return rtrim(rtrim(number_format($n / 1_000_000, 1, ',', '.'), '0'), ',') . ' triệu';
    }
    return number_format($n, 0, ',', '.') . 'đ';
}

function money_range(int|float|null $min, int|float|null $max): string
{
    if (!$max) return 'Theo hồ sơ';
    $a = money_vnd($min); $b = money_vnd($max);
    // "5 triệu – 70 triệu" -> "5 – 70 triệu"
    if (preg_match('/^(.+) (triệu|tỷ)$/u', $a, $ma) && preg_match('/ (triệu|tỷ)$/u', $b, $mb) && $ma[2] === $mb[1]) {
        return $ma[1] . ' – ' . $b;
    }
    return $a . ' – ' . $b;
}

/** Tiêu đề: phần đặt trong *dấu sao* được tô highlight */
function headline_html(?string $text): string
{
    return preg_replace('/\*([^*]+)\*/u', '<mark>$1</mark>', e($text));
}

function normalize_phone(string $p): ?string
{
    $p = preg_replace('/\D+/', '', $p);
    if (str_starts_with($p, '84')) $p = '0' . substr($p, 2);
    return preg_match('/^0[35789]\d{8}$/', $p) ? $p : null;
}

function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$text))));
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fmt_dt(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '';
}

function sign(string $data): string
{
    return substr(hash_hmac('sha256', $data, app_key()), 0, 16);
}

function status_labels(): array
{
    return [
        'new'       => 'Mới',
        'contacted' => 'Đã liên hệ',
        'submitted' => 'Đã nộp hồ sơ',
        'approved'  => 'Được duyệt',
        'rejected'  => 'Từ chối',
        'spam'      => 'Rác / trùng',
    ];
}

function offer_types(): array
{
    return ['loan' => 'Khoản vay', 'card' => 'Thẻ tín dụng'];
}

function provinces(): array
{
    return ['Hà Nội','TP. Hồ Chí Minh','Hải Phòng','Đà Nẵng','Cần Thơ','An Giang','Bắc Ninh','Cà Mau','Cao Bằng','Đắk Lắk','Điện Biên','Đồng Nai','Đồng Tháp','Gia Lai','Hà Tĩnh','Hưng Yên','Huế','Khánh Hòa','Lai Châu','Lâm Đồng','Lạng Sơn','Lào Cai','Nghệ An','Ninh Bình','Phú Thọ','Quảng Ngãi','Quảng Ninh','Quảng Trị','Sơn La','Tây Ninh','Thái Nguyên','Thanh Hóa','Tuyên Quang','Vĩnh Long'];
}

function income_options(): array
{
    return ['duoi-5' => 'Dưới 5 triệu', '5-10' => '5 – 10 triệu', '10-20' => '10 – 20 triệu', '20-50' => '20 – 50 triệu', 'tren-50' => 'Trên 50 triệu'];
}

function offer_initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name));
    $s = '';
    foreach (array_slice($words, 0, 2) as $w) $s .= mb_strtoupper(mb_substr($w, 0, 1));
    return $s ?: '?';
}

function affiliate_link(array $offer, array $reg, ?array $landing): string
{
    $repl = [
        '{sub_id}'       => 'R' . $reg['id'],
        '{reg_id}'       => (string)$reg['id'],
        '{landing}'      => $landing['slug'] ?? '',
        '{utm_source}'   => (string)($reg['utm_source'] ?? ''),
        '{utm_campaign}' => (string)($reg['utm_campaign'] ?? ''),
    ];
    $u = (string)$offer['affiliate_url'];
    foreach ($repl as $k => $v) $u = str_replace($k, rawurlencode($v), $u);
    return $u;
}

function handle_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Tải ảnh lên không thành công (mã lỗi ' . $f['error'] . ').');
    if ($f['size'] > 1024 * 1024) throw new RuntimeException('Ảnh vượt quá 1MB. Hãy nén ảnh rồi tải lại.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Chỉ nhận ảnh PNG, JPG, WEBP hoặc GIF.');
    $name = date('Ym') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dir = cfg('upload_dir');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Không lưu được ảnh vào thư mục uploads.');
    return '/uploads/' . $name;
}
