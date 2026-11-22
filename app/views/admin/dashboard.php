<?php
$actions = '<div class="seg">';
foreach (['7' => '7 ngày', '30' => '30 ngày', '90' => '90 ngày'] as $k => $v) {
    $actions .= '<a href="/admin?range=' . $k . '" class="' . ((string)$range === $k ? 'is-on' : '') . '">' . $v . '</a>';
}
$actions .= '</div>';
$cr = $stats['views'] ? round($stats['regs'] / $stats['views'] * 100, 1) : 0;
$clickRate = $stats['regs'] ? round($stats['clicked'] / $stats['regs'] * 100) : 0;
$maxRegs = max(1, max(array_column($daily, 'regs')));
$statusL = status_labels();
?>
<div class="kpis">
  <a class="kpi" href="/admin/leads?status=new"><span>Chờ xử lý</span><strong><?= number_format($stats['new']) ?></strong><em>đăng ký trạng thái Mới</em></a>
  <div class="kpi"><span>Hôm nay</span><strong><?= number_format($stats['today']) ?></strong><em>đăng ký mới</em></div>
  <div class="kpi"><span>Đăng ký <?= $range ?> ngày</span><strong><?= number_format($stats['regs']) ?></strong><em><?= number_format($stats['customers']) ?> khách hàng khác nhau</em></div>
  <div class="kpi"><span>Tỉ lệ chuyển đổi</span><strong><?= $cr ?>%</strong><em><?= number_format($stats['views']) ?> lượt xem landing</em></div>
  <div class="kpi"><span>Sang trang đối tác</span><strong><?= $clickRate ?>%</strong><em><?= number_format($stats['clicked']) ?> lượt bấm tiếp tục</em></div>
</div>

<section class="card">
  <h2 class="card__t">Đăng ký theo ngày</h2>
  <div class="chart" role="img" aria-label="Biểu đồ số đăng ký theo ngày trong <?= $range ?> ngày">
    <?php foreach ($daily as $d => $v): ?>
      <div class="chart__col" title="<?= date('d/m', strtotime($d)) ?>: <?= $v['regs'] ?> đăng ký, <?= $v['views'] ?> lượt xem">
        <span class="chart__bar" style="height: <?= round($v['regs'] / $maxRegs * 100) ?>%"></span>
        <?php if ($v['regs']): ?><b><?= $v['regs'] ?></b><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="chart__axis"><span><?= date('d/m', strtotime(array_key_first($daily))) ?></span><span><?= date('d/m', strtotime(array_key_last($daily))) ?></span></div>
</section>

<div class="grid2">
  <section class="card">
    <h2 class="card__t">Theo gói vay / thẻ</h2>
    <table class="t">
      <thead><tr><th>Gói</th><th class="r">Đăng ký</th><th class="r">Sang đối tác</th><th class="r">Được duyệt</th></tr></thead>
      <tbody>
      <?php foreach ($byOffer as $o): ?>
        <tr>
          <td><a href="/admin/leads?offer=<?= $o['id'] ?>"><?= e($o['name']) ?></a> <span class="chip chip--<?= e($o['type']) ?>"><?= e(offer_types()[$o['type']] ?? '') ?></span></td>
          <td class="r"><?= (int)$o['regs'] ?></td>
          <td class="r"><?= (int)$o['clicked'] ?></td>
          <td class="r"><?= (int)$o['approved'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2 class="card__t">Theo landing page</h2>
    <table class="t">
      <thead><tr><th>Landing</th><th class="r">Lượt xem</th><th class="r">Đăng ký</th><th class="r">Tỉ lệ</th></tr></thead>
      <tbody>
      <?php foreach ($byLanding as $l): ?>
        <tr>
          <td><a href="/admin/leads?landing=<?= $l['id'] ?>"><?= e($l['name']) ?></a></td>
          <td class="r"><?= number_format((int)$l['views']) ?></td>
          <td class="r"><?= (int)$l['regs'] ?></td>
          <td class="r"><?= $l['views'] ? round($l['regs'] / $l['views'] * 100, 1) . '%' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <h2 class="card__t" style="margin-top:24px">Theo nguồn (utm_source)</h2>
    <?php if (!$bySource): ?><p class="muted">Chưa có dữ liệu trong khoảng thời gian này.</p><?php endif; ?>
    <ul class="bars">
      <?php $maxS = max([1, ...array_column($bySource, 'c')]); foreach ($bySource as $s): ?>
        <li><span><?= e($s['src']) ?></span><i style="--w: <?= round($s['c'] / $maxS * 100) ?>%"></i><b><?= (int)$s['c'] ?></b></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2 class="card__t">Đăng ký gần nhất</h2><a href="/admin/leads">Xem tất cả</a></div>
  <?php if (!$recent): ?>
    <p class="muted">Chưa có đăng ký nào. Khi khách gửi form trên landing page, họ sẽ xuất hiện ở đây.</p>
  <?php else: ?>
  <div class="tscroll"><table class="t">
    <thead><tr><th>Thời gian</th><th>Khách hàng</th><th>Gói</th><th>Trạng thái</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td class="nowrap"><?= fmt_dt($r['created_at']) ?></td>
        <td><a href="/admin/customers/<?= $r['customer_id'] ?>"><?= e($r['full_name']) ?></a><div class="muted"><?= e($r['phone']) ?></div></td>
        <td><?= e($r['offer_name']) ?></td>
        <td><span class="st st--<?= e($r['status']) ?>"><?= e($statusL[$r['status']] ?? $r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
