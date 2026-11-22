<?php $err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . e($errors[$k]) . '</small>' : ''; ?>
<form method="post" action="/admin/profile" class="card stack narrow">
  <?= csrf_field() ?>
  <p class="muted">Email đăng nhập: <b><?= e($u['email']) ?></b> · Vai trò: <?= e(ROLES[$u['role']] ?? '') ?></p>
  <label class="f"><span>Họ tên</span><input name="name" value="<?= e($u['name']) ?>" required><?= $err('name') ?></label>
  <label class="f"><span>Mật khẩu mới (để trống nếu giữ nguyên)</span><input type="password" name="password" autocomplete="new-password"><?= $err('password') ?></label>
  <label class="f"><span>Mật khẩu hiện tại (bắt buộc để lưu)</span><input type="password" name="current_password" required autocomplete="current-password"><?= $err('current_password') ?></label>
  <div class="btns"><button class="b b--primary" type="submit">Lưu thay đổi</button></div>
</form>
