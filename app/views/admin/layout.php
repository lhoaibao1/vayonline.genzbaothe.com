<?php
$me = current_admin();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$newCount = can('leads.view') ? (int)val("SELECT COUNT(*) FROM registrations WHERE status = 'new'") : 0;
$nav = [
    ['/admin', 'Tổng quan', 'leads.view', true],
    ['/admin/leads', 'Khách đăng ký', 'leads.view', false],
    ['/admin/customers', 'Khách hàng', 'leads.view', false],
    ['/admin/offers', 'Gói vay & thẻ', 'offers', false],
    ['/admin/landings', 'Landing page', 'landings', false],
    ['/admin/users', 'Tài khoản quản trị', 'admins', false],
    ['/admin/settings', 'Cài đặt website', 'settings', false],
];
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Quản trị') ?> – <?= e(setting('site_name')) ?></title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
</head>
<body class="adm">
<input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
<aside class="side">
  <a class="side__brand" href="/admin"><span class="brand__mark" aria-hidden="true"></span><?= e(setting('site_name')) ?></a>
  <nav class="side__nav">
    <?php foreach ($nav as [$href, $label, $perm, $exact]): if (!can($perm)) continue;
      $active = $exact ? $path === $href : str_starts_with($path, $href); ?>
      <a href="<?= $href ?>" class="<?= $active ? 'is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
        <?= e($label) ?>
        <?php if ($href === '/admin/leads' && $newCount): ?><span class="pill"><?= $newCount ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <a href="/" target="_blank" rel="noopener">Xem website</a>
    <a href="/admin/profile"><?= e($me['name'] ?? '') ?> · <?= e(ROLES[$me['role'] ?? ''] ?? '') ?></a>
    <form method="post" action="/admin/logout"><?= csrf_field() ?><button type="submit">Đăng xuất</button></form>
  </div>
</aside>
<label for="nav-toggle" class="side__scrim" aria-hidden="true"></label>

<div class="main">
  <header class="mtop">
    <label for="nav-toggle" class="mtop__burger" aria-label="Mở menu">☰</label>
    <h1><?= e($title ?? '') ?></h1>
    <?= $actions ?? '' ?>
  </header>
  <?php foreach (flashes() as [$type, $msg]): ?>
    <div class="flash flash--<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <div class="content"><?= $content ?></div>
</div>
<script>window.ADM = { csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="/assets/js/admin.js?v=1" defer></script>
</body>
</html>
