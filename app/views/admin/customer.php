<?php
$statusL = status_labels();
$edit = can('leads.edit');
$actions = '<a class="b b--ghost" href="/admin/customers">Danh sách khách hàng</a>';
?>
<div class="grid-side">
  <section class="card">
    <h2 class="card__t">Lịch sử đăng ký (<?= count($regs) ?>)</h2>
    <?php if (!$regs): ?><p class="muted">Khách chưa có đăng ký nào.</p><?php endif; ?>
    <ol class="timeline">
      <?php foreach ($regs as $r): ?>
        <li>
          <div class="timeline__head">
            <div>
              <b><?= e($r['current_offer_name'] ?? $r['offer_name']) ?></b>
              <?php if ($r['offer_type']): ?><span class="chip chip--<?= e($r['offer_type']) ?>"><?= e(offer_types()[$r['offer_type']]) ?></span><?php endif; ?>
              <div class="muted">R<?= $r['id'] ?> · <?= fmt_dt($r['created_at']) ?> · qua <?= e($r['landing_name'] ?? 'không rõ landing') ?></div>
            </div>
            <span class="st st--<?= e($r['status']) ?>"><?= e($statusL[$r['status']] ?? '') ?></span>
          </div>
          <dl class="kv">
            <?php if ($r['amount']): ?><div><dt>Số tiền</dt><dd><?= e(money_vnd($r['amount'], false)) ?><?= $r['term'] ? ' · ' . (int)$r['term'] . ' tháng' : '' ?></dd></div><?php endif; ?>
            <div><dt>Sang trang đối tác</dt><dd><?= $r['clicked_out_at'] ? fmt_dt($r['clicked_out_at']) : 'Chưa' ?></dd></div>
            <?php if ($r['utm_source'] || $r['utm_campaign']): ?><div><dt>UTM</dt><dd><?= e(implode(' / ', array_filter([$r['utm_source'], $r['utm_medium'], $r['utm_campaign'], $r['utm_content']]))) ?></dd></div><?php endif; ?>
            <?php if ($r['referrer']): ?><div><dt>Trang giới thiệu</dt><dd class="break"><?= e($r['referrer']) ?></dd></div><?php endif; ?>
            <div><dt>IP / thiết bị</dt><dd class="break muted"><?= e($r['ip']) ?> · <?= e(mb_strimwidth((string)$r['user_agent'], 0, 80, '…')) ?></dd></div>
          </dl>
          <?php if ($edit): ?>
          <form method="post" action="/admin/leads/status" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <input type="hidden" name="back" value="/admin/customers/<?= $c['id'] ?>">
            <select name="status" aria-label="Trạng thái">
              <?php foreach ($statusL as $k => $v): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <input name="note" value="<?= e($r['note']) ?>" placeholder="Ghi chú cho đăng ký này" maxlength="1000">
            <button class="b" type="submit">Lưu</button>
          </form>
          <?php elseif ($r['note']): ?><p class="muted">Ghi chú: <?= e($r['note']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="card">
    <h2 class="card__t">Thông tin khách hàng</h2>
    <form method="post" action="/admin/customers/<?= $c['id'] ?>" class="stack">
      <?= csrf_field() ?>
      <fieldset <?= $edit ? '' : 'disabled' ?> class="stack">
        <label class="f"><span>Họ tên</span><input name="full_name" value="<?= e($c['full_name']) ?>" required></label>
        <label class="f"><span>Số điện thoại</span><input name="phone" value="<?= e($c['phone']) ?>" required></label>
        <label class="f"><span>Email</span><input type="email" name="email" value="<?= e($c['email']) ?>"></label>
        <label class="f"><span>Tỉnh/TP</span><select name="province"><option value="">—</option>
          <?php foreach (provinces() as $p): ?><option <?= $c['province'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span>Thu nhập</span><select name="income"><option value="">—</option>
          <?php foreach (income_options() as $k => $v): ?><option value="<?= $k ?>" <?= $c['income'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span>Ghi chú chăm sóc</span><textarea name="note" rows="4" placeholder="Ví dụ: hẹn gọi lại 15h, đã gửi hồ sơ…"><?= e($c['note']) ?></textarea></label>
        <?php if ($edit): ?><button class="b b--primary" type="submit">Lưu thông tin</button><?php endif; ?>
      </fieldset>
    </form>
    <p class="muted small">Tạo lúc <?= fmt_dt($c['created_at']) ?> · cập nhật <?= fmt_dt($c['updated_at']) ?></p>
    <div class="quick">
      <a class="b" href="tel:<?= e($c['phone']) ?>">Gọi khách</a>
      <a class="b" href="https://zalo.me/<?= e($c['phone']) ?>" target="_blank" rel="noopener">Nhắn Zalo</a>
    </div>
    <?php if (can('leads.delete')): ?>
    <form method="post" action="/admin/customers/<?= $c['id'] ?>/delete" data-confirm="Xoá khách hàng này cùng toàn bộ <?= count($regs) ?> đăng ký? Không thể hoàn tác.">
      <?= csrf_field() ?><button class="b b--danger-ghost" type="submit">Xoá khách hàng</button>
    </form>
    <?php endif; ?>
  </section>
</div>
