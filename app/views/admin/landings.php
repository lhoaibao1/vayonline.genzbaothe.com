<?php $actions = '<a class="b b--primary" href="/admin/landings/new">Tạo landing</a>'; ?>
<div class="card card--flush">
  <div class="tscroll"><table class="t">
    <thead><tr><th>Landing</th><th>Đường dẫn</th><th>Hiển thị gói</th><th class="r">Xem 30 ngày</th><th class="r">Đăng ký 30 ngày</th><th class="r">Tỉ lệ</th><th class="r">Tổng đăng ký</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($landings as $l):
      $path = $l['is_default'] ? '/' : '/lp/' . $l['slug'];
      $cnt = count(array_filter(explode(',', (string)$l['offer_ids']))); ?>
      <tr class="<?= $l['active'] ? '' : 'is-off' ?>">
        <td><a href="/admin/landings/<?= $l['id'] ?>"><b><?= e($l['name']) ?></b></a>
          <?php if ($l['is_default']): ?><span class="chip chip--loan">Trang chủ</span><?php endif; ?>
          <?php if (!$l['active']): ?><span class="chip">Đang tắt</span><?php endif; ?>
          <div class="muted"><?= e(mb_strimwidth((string)$l['headline'], 0, 70, '…')) ?></div></td>
        <td><code><?= e($path) ?></code><?php if ($l['is_default']): ?><div class="muted"><code>/lp/<?= e($l['slug']) ?></code></div><?php endif; ?></td>
        <td><?= ['all' => 'Vay & thẻ', 'loan' => 'Chỉ khoản vay', 'card' => 'Chỉ thẻ'][$l['offer_type']] ?><?= $cnt ? ' · ' . $cnt . ' gói chọn riêng' : '' ?></td>
        <td class="r"><?= number_format((int)$l['views30']) ?></td>
        <td class="r"><a href="/admin/leads?landing=<?= $l['id'] ?>"><?= (int)$l['regs30'] ?></a></td>
        <td class="r"><?= $l['views30'] ? round($l['regs30'] / $l['views30'] * 100, 1) . '%' : '—' ?></td>
        <td class="r"><?= (int)$l['regs'] ?></td>
        <td class="nowrap r"><a href="<?= e($path) ?>" target="_blank" rel="noopener">Xem</a> · <a href="/admin/landings/<?= $l['id'] ?>">Sửa</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<p class="muted small">Gắn UTM vào link quảng cáo để biết khách đến từ đâu, ví dụ:
  <code>/lp/mo-the-tin-dung?utm_source=facebook&amp;utm_campaign=the-thang-11</code>. Lượt xem được tính một lần mỗi phiên mỗi ngày.</p>
