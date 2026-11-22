<?php
$siteName = setting('site_name', 'Vay Online');
$pageTitle = $seoTitle ?? ($landing['seo_title'] ?? '') ?: (($offer['name'] ?? '') ? $offer['name'] . ' – ' . $siteName : $siteName);
$pageDesc = $seoDesc ?? ($landing['seo_description'] ?? '') ?: setting('site_tagline');
$hotline = setting('hotline');
$zalo = setting('zalo');
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#0B3B36">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css?v=1">
<?= setting('head_scripts') ?>
</head>
<body>
<header class="topbar">
  <div class="wrap topbar__in">
    <a class="brand" href="/" aria-label="<?= e($siteName) ?> – trang chủ">
      <span class="brand__mark" aria-hidden="true"></span>
      <span class="brand__name"><?= e($siteName) ?></span>
    </a>
    <nav class="topnav" aria-label="Điều hướng chính">
      <a href="/#goi">So sánh gói</a>
      <a href="/#cach-dang-ky">Cách đăng ký</a>
      <a href="/#hoi-dap">Hỏi đáp</a>
    </nav>
    <?php if ($hotline): ?>
      <a class="topbar__call" href="tel:<?= e(preg_replace('/\D/', '', $hotline)) ?>">Gọi <?= e($hotline) ?></a>
    <?php endif; ?>
  </div>
</header>

<main id="noi-dung"><?= $content ?></main>

<footer class="footer">
  <div class="wrap footer__grid">
    <div>
      <a class="brand brand--light" href="/"><span class="brand__mark" aria-hidden="true"></span><span class="brand__name"><?= e($siteName) ?></span></a>
      <p class="footer__about"><?= nl2br(e(setting('company_info'))) ?></p>
    </div>
    <?php if ($hotline || $zalo || setting('contact_email')): ?>
    <div class="footer__contact">
      <h2>Liên hệ</h2>
      <ul>
        <?php if ($hotline): ?><li>Hotline: <a href="tel:<?= e(preg_replace('/\D/', '', $hotline)) ?>"><?= e($hotline) ?></a></li><?php endif; ?>
        <?php if ($zalo): ?><li>Zalo: <a href="https://zalo.me/<?= e(preg_replace('/\D/', '', $zalo)) ?>" rel="noopener" target="_blank"><?= e($zalo) ?></a></li><?php endif; ?>
        <?php if (setting('contact_email')): ?><li>Email: <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="wrap footer__legal">
    <p><?= nl2br(e(setting('disclaimer'))) ?></p>
    <p>© <?= date('Y') ?> <?= e($siteName) ?></p>
  </div>
</footer>

<?php require APP . '/views/site/_register.php'; ?>
<script>window.APP = { csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="/assets/js/site.js?v=1" defer></script>
<?= setting('body_scripts') ?>
</body>
</html>
