<?php $actions = '<a class="b b--primary" href="/admin/users/new">Thêm tài khoản</a>'; ?>
<div class="card card--flush">
  <div class="tscroll"><table class="t">
    <thead><tr><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="<?= $u['active'] ? '' : 'is-off' ?>">
        <td><b><?= e($u['name']) ?></b></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e(ROLES[$u['role']] ?? $u['role']) ?></td>
        <td><?= $u['active'] ? 'Hoạt động' : 'Đã khoá' ?></td>
        <td><?= $u['last_login_at'] ? fmt_dt($u['last_login_at']) : '<span class="muted">Chưa đăng nhập</span>' ?></td>
        <td class="r"><a href="/admin/users/<?= $u['id'] ?>">Sửa</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<div class="card">
  <h2 class="card__t">Quyền của từng vai trò</h2>
  <table class="t">
    <thead><tr><th>Vai trò</th><th>Được làm gì</th></tr></thead>
    <tbody>
      <tr><td>Quản trị viên</td><td>Toàn quyền: khách đăng ký (xem, sửa, xuất, xoá), gói vay, landing, cài đặt, tài khoản.</td></tr>
      <tr><td>Biên tập nội dung</td><td>Sửa gói vay/thẻ và landing page. Xem danh sách đăng ký, không sửa hay xuất được.</td></tr>
      <tr><td>Tư vấn / chăm sóc khách</td><td>Xem, cập nhật trạng thái, ghi chú và xuất danh sách khách đăng ký.</td></tr>
    </tbody>
  </table>
</div>
