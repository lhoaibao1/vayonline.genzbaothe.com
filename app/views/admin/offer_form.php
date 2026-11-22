<?php
$err = fn($k) => !empty($errors[$k]) ? '<small class="err">' . e($errors[$k]) . '</small>' : '';
$actions = '<a class="b b--ghost" href="/admin/offers">Quay lại danh sách</a>';
?>
<form method="post" action="/admin/offers/save" enctype="multipart/form-data" class="form-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">

  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Thông tin chính</h2>
      <div class="row2">
        <label class="f"><span>Loại sản phẩm</span>
          <select name="type" data-type-select>
            <?php foreach (offer_types() as $k => $v): ?><option value="<?= $k ?>" <?= $o['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select></label>
        <label class="f"><span>Nhãn nổi bật (tuỳ chọn)</span><input name="badge" value="<?= e($o['badge']) ?>" placeholder="Ví dụ: Duyệt nhanh" maxlength="24"></label>
      </div>
      <label class="f"><span>Tên gói</span><input name="name" value="<?= e($o['name']) ?>" required placeholder="Ví dụ: Vay tiêu dùng chỉ cần CCCD"><?= $err('name') ?></label>
      <div class="row2">
        <label class="f"><span>Đối tác / ngân hàng</span><input name="partner" value="<?= e($o['partner']) ?>"></label>
        <label class="f"><span>Đường dẫn</span><input name="slug" value="<?= e($o['slug']) ?>" placeholder="tự tạo từ tên gói"><?= $err('slug') ?><small class="hint">/uu-dai/<?= e($o['slug'] ?: '...') ?></small></label>
      </div>
      <label class="f"><span>Mô tả ngắn</span><textarea name="description" rows="3"><?= e($o['description']) ?></textarea></label>
    </section>

    <section class="card stack">
      <h2 class="card__t">Thông số hiển thị trên bảng so sánh</h2>
      <div class="row2">
        <label class="f"><span>Hạn mức tối thiểu (đ)</span><input name="amount_min" value="<?= e(number_format((int)$o['amount_min'], 0, ',', '.')) ?>" inputmode="numeric" data-money></label>
        <label class="f"><span>Hạn mức tối đa (đ)</span><input name="amount_max" value="<?= e(number_format((int)$o['amount_max'], 0, ',', '.')) ?>" inputmode="numeric" data-money><?= $err('amount_max') ?></label>
      </div>
      <div class="row2">
        <label class="f"><span>Lãi suất (chữ hiển thị)</span><input name="rate_text" value="<?= e($o['rate_text']) ?>" placeholder="Từ 1,6%/tháng hoặc Miễn lãi 55 ngày"></label>
        <label class="f" data-loan-only><span>Lãi suất thấp nhất (%/năm) để tính trả góp</span><input name="rate_min" value="<?= e((string)$o['rate_min']) ?>" inputmode="decimal" placeholder="19.2"></label>
      </div>
      <div class="row2" data-loan-only>
        <label class="f"><span>Kỳ hạn ngắn nhất (tháng)</span><input type="number" min="0" name="term_min" value="<?= (int)$o['term_min'] ?>"></label>
        <label class="f"><span>Kỳ hạn dài nhất (tháng)</span><input type="number" min="0" name="term_max" value="<?= (int)$o['term_max'] ?>"></label>
      </div>
      <div class="row2">
        <label class="f"><span>Độ tuổi</span><input name="age_text" value="<?= e($o['age_text']) ?>" placeholder="20 – 60 tuổi"></label>
        <label class="f"><span>Thời gian duyệt</span><input name="approval_text" value="<?= e($o['approval_text']) ?>" placeholder="Trong 24 giờ"></label>
      </div>
      <label class="f" data-card-only><span>Phí thường niên</span><input name="annual_fee" value="<?= e($o['annual_fee']) ?>" placeholder="Miễn phí năm đầu"></label>
    </section>

    <section class="card stack">
      <h2 class="card__t">Nội dung trang chi tiết</h2>
      <label class="f"><span>Điểm nổi bật – mỗi dòng một ý</span><textarea name="highlights" rows="4"><?= e($o['highlights']) ?></textarea></label>
      <label class="f"><span>Điều kiện đăng ký – mỗi dòng một ý</span><textarea name="requirements" rows="4"><?= e($o['requirements']) ?></textarea></label>
    </section>
  </div>

  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Xuất bản</h2>
      <label class="sw"><input type="checkbox" name="active" value="1" <?= $o['active'] ? 'checked' : '' ?>><span>Hiển thị trên website</span></label>
      <label class="f"><span>Thứ tự (nhỏ hiển thị trước)</span><input type="number" name="sort_order" value="<?= (int)$o['sort_order'] ?>"></label>
      <button class="b b--primary b--block" type="submit">Lưu gói</button>
    </section>

    <section class="card stack">
      <h2 class="card__t">Link đối tác (affiliate)</h2>
      <label class="f"><span>URL chuyển khách sau khi đăng ký</span><input name="affiliate_url" value="<?= e($o['affiliate_url']) ?>" placeholder="https://..."><?= $err('affiliate_url') ?></label>
      <p class="hint">Có thể chèn biến vào link để đối soát với đối tác:
        <code>{sub_id}</code> mã đăng ký (R123), <code>{utm_source}</code>, <code>{utm_campaign}</code>, <code>{landing}</code>.
        Để trống nếu bạn tự gọi tư vấn cho khách.</p>
    </section>

    <section class="card stack">
      <h2 class="card__t">Logo</h2>
      <?php if ($o['logo']): ?>
        <img src="<?= e($o['logo']) ?>" alt="Logo hiện tại" class="logo-prev">
        <label class="sw"><input type="checkbox" name="remove_logo" value="1"><span>Xoá logo</span></label>
      <?php endif; ?>
      <label class="f"><span>Tải ảnh mới (PNG, JPG, WEBP – tối đa 1MB)</span><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif"><?= $err('logo') ?></label>
      <p class="hint">Không có logo thì website hiển thị chữ viết tắt tên đối tác.</p>
    </section>

    <?php if ($o['id']): ?>
    <section class="card">
      <button class="b b--danger-ghost b--block" type="submit" form="del-form">Xoá gói này</button>
    </section>
    <?php endif; ?>
  </div>
</form>
<?php if ($o['id']): ?>
<form id="del-form" method="post" action="/admin/offers/<?= (int)$o['id'] ?>/delete" data-confirm="Xoá gói “<?= e($o['name']) ?>”? Nếu chỉ muốn tạm ngừng, hãy tắt Hiển thị.">
  <?= csrf_field() ?>
</form>
<?php endif; ?>
