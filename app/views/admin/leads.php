<?php
$statusL = status_labels();
$qs = array_filter($f, fn($v) => $v !== '' && $v !== 0);
$back = '/admin/leads' . ($qs || $page > 1 ? '?' . http_build_query($qs + ($page > 1 ? ['page' => $page] : [])) : '');
$actions = can('leads.export') ? '<a class="b" href="/admin/leads/export' . ($qs ? '?' . e(http_build_query($qs)) : '') . '">Xuất Excel (CSV)</a>' : '';
$pages = (int)ceil($total / $per);
?>
<nav class="stabs" aria-label="Lọc theo trạng thái">
  <a href="<?= e(url('admin/leads', array_merge($qs, ['status' => '']))) ?>" class="<?= $f['status'] === '' ? 'is-on' : '' ?>">Tất cả <b><?= array_sum($counts) ?></b></a>
  <?php foreach ($statusL as $k => $v): ?>
    <a href="<?= e(url('admin/leads', array_merge($qs, ['status' => $k]))) ?>" class="<?= $f['status'] === $k ? 'is-on' : '' ?>"><?= e($v) ?> <b><?= $counts[$k] ?? 0 ?></b></a>
  <?php endforeach; ?>
</nav>

<form class="filters card" method="get">
  <?php if ($f['status']): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
  <label class="f f--grow"><span>Tìm kiếm</span><input name="q" value="<?= e($f['q']) ?>" placeholder="Tên, SĐT hoặc email"></label>
  <label class="f"><span>Gói</span><select name="offer"><option value="">Tất cả gói</option>
    <?php foreach ($offers as $o): ?><option value="<?= $o['id'] ?>" <?= $f['offer'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Loại</span><select name="type"><option value="">Vay & thẻ</option>
    <?php foreach (offer_types() as $k => $v): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Landing</span><select name="landing"><option value="">Tất cả landing</option>
    <?php foreach ($landings as $l): ?><option value="<?= $l['id'] ?>" <?= $f['landing'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
  <?php if ($sources): ?>
  <label class="f"><span>Nguồn</span><select name="source"><option value="">Mọi nguồn</option>
    <?php foreach ($sources as $s): ?><option <?= $f['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label class="f"><span>Từ ngày</span><input type="date" name="from" value="<?= e($f['from']) ?>"></label>
  <label class="f"><span>Đến ngày</span><input type="date" name="to" value="<?= e($f['to']) ?>"></label>
  <div class="filters__btns"><button class="b b--primary" type="submit">Lọc</button><?php if ($qs): ?><a class="b b--ghost" href="/admin/leads">Bỏ lọc</a><?php endif; ?></div>
</form>

<form method="post" action="/admin/leads/bulk" class="card card--flush" id="bulk-form">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($back) ?>">
  <div class="bulk">
    <span><b><?= number_format($total) ?></b> đăng ký</span>
    <?php if (can('leads.edit')): ?>
    <div class="bulk__act" data-bulk hidden>
      <span data-bulk-count></span>
      <select name="action" required>
        <option value="">Chọn thao tác…</option>
        <?php foreach ($statusL as $k => $v): ?><option value="<?= $k ?>">Chuyển sang: <?= e($v) ?></option><?php endforeach; ?>
        <?php if (can('leads.delete')): ?><option value="delete">Xoá đăng ký đã chọn</option><?php endif; ?>
      </select>
      <button class="b" type="submit" data-confirm-delete>Áp dụng</button>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$leads): ?>
    <p class="empty-st">Không có đăng ký nào khớp bộ lọc. <a href="/admin/leads">Xem tất cả đăng ký</a></p>
  <?php else: ?>
  <div class="tscroll">
  <table class="t t--leads">
    <thead><tr>
      <?php if (can('leads.edit')): ?><th class="cb"><input type="checkbox" data-check-all aria-label="Chọn tất cả"></th><?php endif; ?>
      <th>Mã / thời gian</th><th>Khách hàng</th><th>Gói đăng ký</th><th>Nguồn</th><th>Trạng thái</th>
    </tr></thead>
    <tbody>
    <?php foreach ($leads as $r): ?>
      <tr>
        <?php if (can('leads.edit')): ?><td class="cb"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" aria-label="Chọn R<?= $r['id'] ?>"></td><?php endif; ?>
        <td class="nowrap"><b>R<?= $r['id'] ?></b><div class="muted"><?= fmt_dt($r['created_at']) ?></div></td>
        <td>
          <a href="/admin/customers/<?= $r['customer_id'] ?>"><b><?= e($r['full_name']) ?></b></a>
          <div><a class="tel" href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?= $r['province'] ? ' · ' . e($r['province']) : '' ?></div>
          <?php if ($r['income']): ?><div class="muted">Thu nhập <?= e(income_options()[$r['income']] ?? '') ?></div><?php endif; ?>
        </td>
        <td>
          <?= e($r['current_offer_name'] ?? $r['offer_name']) ?>
          <?php if ($r['offer_type']): ?><span class="chip chip--<?= e($r['offer_type']) ?>"><?= e(offer_types()[$r['offer_type']]) ?></span><?php endif; ?>
          <?php if ($r['amount']): ?><div class="muted"><?= e(money_vnd($r['amount'])) ?><?= $r['term'] ? ' · ' . (int)$r['term'] . ' tháng' : '' ?></div><?php endif; ?>
          <div class="muted"><?= $r['clicked_out_at'] ? '✓ Đã sang trang đối tác' : 'Chưa sang trang đối tác' ?></div>
        </td>
        <td>
          <?= e($r['landing_name'] ?? '—') ?>
          <?php if ($r['utm_source'] || $r['utm_campaign']): ?><div class="muted"><?= e(implode(' / ', array_filter([$r['utm_source'], $r['utm_medium'], $r['utm_campaign']]))) ?></div><?php endif; ?>
        </td>
        <td>
          <?php if (can('leads.edit')): ?>
            <select class="st-select st--<?= e($r['status']) ?>" data-status="<?= $r['id'] ?>" aria-label="Trạng thái R<?= $r['id'] ?>">
              <?php foreach ($statusL as $k => $v): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
          <?php else: ?>
            <span class="st st--<?= e($r['status']) ?>"><?= e($statusL[$r['status']] ?? '') ?></span>
          <?php endif; ?>
          <?php if ($r['note']): ?><div class="muted note" title="<?= e($r['note']) ?>"><?= e(mb_strimwidth($r['note'], 0, 60, '…')) ?></div><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</form>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Phân trang">
  <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
    <a href="<?= e(url('admin/leads', $qs + ['page' => $i])) ?>" class="<?= $i === $page ? 'is-on' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <span class="muted">Trang <?= $page ?>/<?= $pages ?></span>
</nav>
<?php endif; ?>
