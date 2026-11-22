<?php
$err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . e($errors[$k]) . '</small>' : '';
$actions = '<a class="b b--ghost" href="/admin/users">Quay lại</a>';
?>
<form method="post" action="/admin/users/save" class="card stack narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
  <label class="f"><span>Họ tên</span><input name="name" value="<?= e($u['name']) ?>" required><?= $err('name') ?></label>
  <label class="f"><span>Email đăng nhập</span><input type="email" name="email" value="<?= e($u['email']) ?>" required><?= $err('email') ?></label>
  <label class="f"><span><?= $u['id'] ? 'Mật khẩu mới (để trống nếu giữ nguyên)' : 'Mật khẩu (ít nhất 8 ký tự)' ?></span><input type="password" name="password" autocomplete="new-password" <?= $u['id'] ? '' : 'required' ?>><?= $err('password') ?></label>
  <label class="f"><span>Vai trò</span><select name="role">
    <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select><?= $err('role') ?></label>
  <label class="sw"><input type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>><span>Cho phép đăng nhập</span></label>
  <div class="btns">
    <button class="b b--primary" type="submit">Lưu tài khoản</button>
    <?php if ($u['id'] && (int)$u['id'] !== (int)current_admin()['id']): ?>
      <button class="b b--danger-ghost" type="submit" form="del-form">Xoá tài khoản</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($u['id']): ?>
<form id="del-form" method="post" action="/admin/users/<?= (int)$u['id'] ?>/delete" data-confirm="Xoá tài khoản <?= e($u['email']) ?>?"><?= csrf_field() ?></form>
<?php endif; ?>
