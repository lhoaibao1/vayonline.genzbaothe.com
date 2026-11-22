<?php
declare(strict_types=1);

const ROLES = [
    'admin'  => 'Quản trị viên',
    'editor' => 'Biên tập nội dung',
    'sales'  => 'Tư vấn / chăm sóc khách',
];

// Quyền theo vai trò
const PERMS = [
    'admin'  => ['leads.view', 'leads.edit', 'leads.export', 'leads.delete', 'offers', 'landings', 'settings', 'admins'],
    'editor' => ['leads.view', 'offers', 'landings'],
    'sales'  => ['leads.view', 'leads.edit', 'leads.export'],
];

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) return $admin;
    $id = $_SESSION['admin_id'] ?? null;
    $admin = $id ? row('SELECT * FROM admins WHERE id = ? AND active = 1', [$id]) : null;
    return $admin;
}

function can(string $perm): bool
{
    $a = current_admin();
    return $a && in_array($perm, PERMS[$a['role']] ?? [], true);
}

function require_admin(?string $perm = null): array
{
    $a = current_admin();
    if (!$a) redirect(url('admin/login', ['next' => $_SERVER['REQUEST_URI'] ?? '']));
    if ($perm && !can($perm)) {
        http_response_code(403);
        view('admin/403', ['title' => 'Không có quyền'], 'admin/layout');
        exit;
    }
    return $a;
}

function login_throttled(string $email): bool
{
    $key = 'login_fail_' . sha1(client_ip());
    $data = json_decode((string)setting($key, '[]'), true) ?: [];
    $data = array_filter($data, fn($t) => $t > time() - 900);
    return count($data) >= 8;
}

function login_record_fail(): void
{
    $key = 'login_fail_' . sha1(client_ip());
    $data = json_decode((string)setting($key, '[]'), true) ?: [];
    $data = array_values(array_filter($data, fn($t) => $t > time() - 900));
    $data[] = time();
    set_setting($key, json_encode($data));
}
