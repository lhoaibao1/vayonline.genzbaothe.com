<?php $actions = '<a class="b b--primary" href="/admin/offers/new">Thêm gói</a>'; ?>
<div class="card card--flush">
  <?php if (!$offers): ?>
    <p class="empty-st">Chưa có gói nào. <a href="/admin/offers/new">Thêm gói vay hoặc thẻ đầu tiên</a></p>
  <?php else: ?>
  <div class="tscroll"><table class="t">
    <thead><tr><th>Gói</th><th>Loại</th><th>Hạn mức</th><th>Lãi suất</th><th class="r">Đăng ký 30 ngày</th><th class="r">Tổng</th><th class="r">Thứ tự</th><th>Hiển thị</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($offers as $o): ?>
      <tr class="<?= $o['active'] ? '' : 'is-off' ?>">
        <td>
          <div class="ofr">
            <?php if ($o['logo']): ?><img src="<?= e($o['logo']) ?>" alt="" class="ofr__logo"><?php else: ?><span class="ofr__logo"><?= e(offer_initials($o['partner'] ?: $o['name'])) ?></span><?php endif; ?>
            <div><a href="/admin/offers/<?= $o['id'] ?>"><b><?= e($o['name']) ?></b></a><div class="muted"><?= e($o['partner']) ?><?= !$o['affiliate_url'] ? ' · <span class="warn">chưa có link đối tác</span>' : '' ?></div></div>
          </div>
        </td>
        <td><span class="chip chip--<?= e($o['type']) ?>"><?= e(offer_types()[$o['type']]) ?></span></td>
        <td class="nowrap"><?= e(money_range($o['amount_min'], $o['amount_max'])) ?></td>
        <td><?= e($o['rate_text']) ?></td>
        <td class="r"><a href="/admin/leads?offer=<?= $o['id'] ?>"><?= (int)$o['regs30'] ?></a></td>
        <td class="r"><?= (int)$o['regs'] ?></td>
        <td class="r"><?= (int)$o['sort_order'] ?></td>
        <td>
          <form method="post" action="/admin/offers/<?= $o['id'] ?>/toggle"><?= csrf_field() ?>
            <button class="toggle <?= $o['active'] ? 'is-on' : '' ?>" type="submit" aria-label="<?= $o['active'] ? 'Đang hiển thị – bấm để ẩn' : 'Đang ẩn – bấm để hiển thị' ?>"><span></span></button>
          </form>
        </td>
        <td class="nowrap r">
          <a href="/uu-dai/<?= e($o['slug']) ?>" target="_blank" rel="noopener">Xem</a> ·
          <a href="/admin/offers/<?= $o['id'] ?>">Sửa</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<p class="muted small">Thứ tự nhỏ hiển thị trước. Gói bị ẩn không xuất hiện trên landing nhưng dữ liệu đăng ký cũ vẫn được giữ.</p>
