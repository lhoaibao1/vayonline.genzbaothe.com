<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/site.php';
require APP . '/admin.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

// Cho phép `php -S` phục vụ file tĩnh trực tiếp
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$routes = [
    ['GET',  '#^/$#',                              'site_home'],
    ['GET',  '#^/lp/([a-z0-9-]+)$#',               'site_landing'],
    ['GET',  '#^/uu-dai/([a-z0-9-]+)$#',           'site_offer'],
    ['POST', '#^/dang-ky$#',                       'site_register'],
    ['GET',  '#^/di/(\d+)-([a-f0-9]{16})$#',       'site_go'],

    ['GET',  '#^/admin$#',                         'admin_dashboard'],
    ['GET',  '#^/admin/login$#',                   'admin_login'],
    ['POST', '#^/admin/login$#',                   'admin_login_post'],
    ['GET',  '#^/admin/setup$#',                   'admin_setup'],
    ['POST', '#^/admin/setup$#',                   'admin_setup_post'],
    ['POST', '#^/admin/logout$#',                  'admin_logout'],

    ['GET',  '#^/admin/leads$#',                   'admin_leads'],
    ['GET',  '#^/admin/leads/export$#',            'admin_leads_export'],
    ['POST', '#^/admin/leads/status$#',            'admin_leads_status'],
    ['POST', '#^/admin/leads/bulk$#',              'admin_leads_bulk'],
    ['GET',  '#^/admin/customers$#',               'admin_customers'],
    ['GET',  '#^/admin/customers/(\d+)$#',         'admin_customer'],
    ['POST', '#^/admin/customers/(\d+)$#',         'admin_customer_save'],
    ['POST', '#^/admin/customers/(\d+)/delete$#',  'admin_customer_delete'],

    ['GET',  '#^/admin/offers$#',                  'admin_offers'],
    ['GET',  '#^/admin/offers/new$#',              'admin_offer_form'],
    ['GET',  '#^/admin/offers/(\d+)$#',            'admin_offer_form'],
    ['POST', '#^/admin/offers/save$#',             'admin_offer_save'],
    ['POST', '#^/admin/offers/(\d+)/delete$#',     'admin_offer_delete'],
    ['POST', '#^/admin/offers/(\d+)/toggle$#',     'admin_offer_toggle'],

    ['GET',  '#^/admin/landings$#',                'admin_landings'],
    ['GET',  '#^/admin/landings/new$#',            'admin_landing_form'],
    ['GET',  '#^/admin/landings/(\d+)$#',          'admin_landing_form'],
    ['POST', '#^/admin/landings/save$#',           'admin_landing_save'],
    ['POST', '#^/admin/landings/(\d+)/delete$#',   'admin_landing_delete'],

    ['GET',  '#^/admin/users$#',                   'admin_users'],
    ['GET',  '#^/admin/users/new$#',               'admin_user_form'],
    ['GET',  '#^/admin/users/(\d+)$#',             'admin_user_form'],
    ['POST', '#^/admin/users/save$#',              'admin_user_save'],
    ['POST', '#^/admin/users/(\d+)/delete$#',      'admin_user_delete'],

    ['GET',  '#^/admin/settings$#',                'admin_settings'],
    ['POST', '#^/admin/settings$#',                'admin_settings_save'],
    ['GET',  '#^/admin/profile$#',                 'admin_profile'],
    ['POST', '#^/admin/profile$#',                 'admin_profile_save'],
];

try {
    foreach ($routes as [$m, $re, $handler]) {
        if ($m === $method && preg_match($re, $path, $mm)) {
            array_shift($mm);
            $handler(...$mm);
            exit;
        }
    }
    not_found();
} catch (Throwable $ex) {
    error_log((string)$ex);
    http_response_code(500);
    echo cfg('debug') ? '<pre>' . e((string)$ex) . '</pre>' : 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau ít phút.';
}
