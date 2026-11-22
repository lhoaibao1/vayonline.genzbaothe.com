<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Đăng nhập quản trị</title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
</head>
<body class="auth">
<form class="auth__box" method="post" action="/admin/login<?= isset($_GET['next']) ? '?next=' . e(urlencode((string)$_GET['next'])) : '' ?>">
  <?= csrf_field() ?>
  <p class="auth__brand"><span class="brand__mark" aria-hidden="true"></span><?= e(setting('site_name')) ?></p>
  <h1>Đăng nhập quản trị</h1>
  <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
  <label class="f"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username"></label>
  <label class="f"><span>Mật khẩu</span><input type="password" name="password" required autocomplete="current-password"></label>
  <button class="b b--primary b--block" type="submit">Đăng nhập</button>
</form>
</body>
</html>
