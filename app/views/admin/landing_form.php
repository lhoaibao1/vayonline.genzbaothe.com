<?php
$err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . e($errors[$k]) . '</small>' : '';
$selected = array_filter(array_map('intval', explode(',', (string)$l['offer_ids'])));
$actions = '<a class="b b--ghost" href="/admin/landings">Quay lại danh sách</a>';
?>
<form method="post" action="/admin/landings/save" class="form-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Nội dung</h2>
      <div class="row2">
        <label class="f"><span>Tên nội bộ (dùng trong báo cáo)</span><input name="name" value="<?= e($l['name']) ?>" required placeholder="Ví dụ: FB – Vay nhanh tháng 11"><?= $err('name') ?></label>
        <label class="f"><span>Đường dẫn</span><input name="slug" value="<?= e($l['slug']) ?>" placeholder="tự tạo từ tên"><?= $err('slug') ?><small class="hint">/lp/<?= e($l['slug'] ?: '...') ?></small></label>
      </div>
      <label class="f"><span>Tiêu đề chính (H1)</span><input name="headline" value="<?= e($l['headline']) ?>" required maxlength="120"><?= $err('headline') ?><small class="hint">Đặt cụm muốn tô nổi bật trong *dấu sao*, ví dụ: Cần tiền gấp? *Chọn gói, duyệt liền.*</small></label>
      <label class="f"><span>Mô tả dưới tiêu đề</span><textarea name="subheadline" rows="2" maxlength="300"><?= e($l['subheadline']) ?></textarea></label>
      <label class="sw"><input type="checkbox" name="show_calculator" value="1" <?= $l['show_calculator'] ? 'checked' : '' ?>><span>Hiện công cụ tính khoản vay (chỉ khi landing có gói vay)</span></label>
    </section>

    <section class="card stack">
      <h2 class="card__t">Gói hiển thị</h2>
      <label class="f"><span>Loại sản phẩm</span>
        <select name="offer_type">
          <option value="all" <?= $l['offer_type'] === 'all' ? 'selected' : '' ?>>Cả khoản vay và thẻ tín dụng</option>
          <option value="loan" <?= $l['offer_type'] === 'loan' ? 'selected' : '' ?>>Chỉ khoản vay</option>
          <option value="card" <?= $l['offer_type'] === 'card' ? 'selected' : '' ?>>Chỉ thẻ tín dụng</option>
        </select></label>
      <fieldset class="pick">
        <legend>Chọn gói cụ thể <span class="muted">(không chọn = hiện mọi gói đang bật thuộc loại trên)</span></legend>
        <?php foreach ($offers as $o): ?>
          <label class="sw"><input type="checkbox" name="offer_ids[]" value="<?= $o['id'] ?>" <?= in_array((int)$o['id'], $selected, true) ? 'checked' : '' ?>>
            <span><?= e($o['name']) ?> <span class="chip chip--<?= e($o['type']) ?>"><?= e(offer_types()[$o['type']]) ?></span><?= $o['active'] ? '' : ' <span class="muted">(đang ẩn)</span>' ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    </section>

    <section class="card stack">
      <h2 class="card__t">SEO & chia sẻ mạng xã hội</h2>
      <label class="f"><span>Tiêu đề trang (thẻ title)</span><input name="seo_title" value="<?= e($l['seo_title']) ?>" maxlength="70"></label>
      <label class="f"><span>Mô tả (meta description)</span><textarea name="seo_description" rows="2" maxlength="170"><?= e($l['seo_description']) ?></textarea></label>
    </section>
  </div>

  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Xuất bản</h2>
      <label class="sw"><input type="checkbox" name="active" value="1" <?= $l['active'] ? 'checked' : '' ?>><span>Đang hoạt động</span></label>
      <label class="sw"><input type="checkbox" name="is_default" value="1" <?= $l['is_default'] ? 'checked' : '' ?>><span>Dùng làm trang chủ (/)</span></label>
      <button class="b b--primary b--block" type="submit">Lưu landing</button>
      <?php if ($l['id']): ?><a class="b b--block" href="<?= $l['is_default'] ? '/' : '/lp/' . e($l['slug']) ?>" target="_blank" rel="noopener">Xem trang</a><?php endif; ?>
    </section>
    <?php if ($l['id'] && !$l['is_default']): ?>
    <section class="card"><button class="b b--danger-ghost b--block" type="submit" form="del-form">Xoá landing</button></section>
    <?php endif; ?>
  </div>
</form>
<?php if ($l['id']): ?>
<form id="del-form" method="post" action="/admin/landings/<?= (int)$l['id'] ?>/delete" data-confirm="Xoá landing “<?= e($l['name']) ?>”? Đăng ký cũ vẫn giữ nhưng không còn gắn với landing này."><?= csrf_field() ?></form>
<?php endif; ?>
