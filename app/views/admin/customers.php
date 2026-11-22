<?php $pages = (int)ceil($total / $per); ?>
<form class="filters card" method="get">
  <label class="f f--grow"><span>Tìm khách hàng</span><input name="q" value="<?= e($q) ?>" placeholder="Tên, SĐT hoặc email"></label>
  <div class="filters__btns"><button class="b b--primary" type="submit">Tìm</button><?php if ($q): ?><a class="b b--ghost" href="/admin/customers">Bỏ lọc</a><?php endif; ?></div>
</form>

<div class="card card--flush">
  <div class="bulk"><span><b><?= number_format($total) ?></b> khách hàng (gộp theo số điện thoại)</span></div>
  <?php if (!$customers): ?>
    <p class="empty-st">Không tìm thấy khách hàng nào.</p>
  <?php else: ?>
  <div class="tscroll"><table class="t">
    <thead><tr><th>Khách hàng</th><th>Tỉnh/TP</th><th>Các gói đã đăng ký</th><th class="r">Số lần</th><th>Lần gần nhất</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): ?>
      <tr>
        <td><a href="/admin/customers/<?= $c['id'] ?>"><b><?= e($c['full_name']) ?></b></a><div><a class="tel" href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></div></td>
        <td><?= e($c['province']) ?></td>
        <td class="wrap-cell"><?php foreach (array_unique(array_filter(explode(' | ', (string)$c['offer_list']))) as $on): ?><span class="chip"><?= e($on) ?></span> <?php endforeach; ?></td>
        <td class="r"><?= (int)$c['regs'] ?></td>
        <td class="nowrap"><?= fmt_dt($c['last_reg']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Phân trang">
  <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
    <a href="<?= e(url('admin/customers', ['q' => $q, 'page' => $i])) ?>" class="<?= $i === $page ? 'is-on' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
