<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Tạo tài khoản quản trị đầu tiên</title>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
</head>
<body class="auth">
<form class="auth__box" method="post" action="/admin/setup">
  <?= csrf_field() ?>
  <p class="auth__brand"><span class="brand__mark" aria-hidden="true"></span><?= e(setting('site_name')) ?></p>
  <h1>Tạo tài khoản quản trị</h1>
  <p class="muted">Website chưa có tài khoản nào. Tài khoản bạn tạo ở đây có toàn quyền quản trị.</p>
  <label class="f"><span>Họ tên</span><input name="name" value="<?= e(old('name')) ?>" required><?php if (!empty($errors['name'])): ?><small class="err"><?= e($errors['name']) ?></small><?php endif; ?></label>
  <label class="f"><span>Email đăng nhập</span><input type="email" name="email" value="<?= e(old('email')) ?>" required><?php if (!empty($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?></label>
  <label class="f"><span>Mật khẩu (ít nhất 8 ký tự)</span><input type="password" name="password" required minlength="8" autocomplete="new-password"><?php if (!empty($errors['password'])): ?><small class="err"><?= e($errors['password']) ?></small><?php endif; ?></label>
  <button class="b b--primary b--block" type="submit">Tạo tài khoản</button>
</form>
</body>
</html>
