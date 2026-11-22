<form method="post" action="/admin/settings" class="form-grid">
  <?= csrf_field() ?>
  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Thông tin website</h2>
      <div class="row2">
        <label class="f"><span>Tên website</span><input name="site_name" value="<?= e($s['site_name']) ?>" required></label>
        <label class="f"><span>Khẩu hiệu (mô tả mặc định)</span><input name="site_tagline" value="<?= e($s['site_tagline']) ?>"></label>
      </div>
      <div class="row2">
        <label class="f"><span>Hotline</span><input name="hotline" value="<?= e($s['hotline']) ?>" placeholder="1900 xxxx"></label>
        <label class="f"><span>Số Zalo</span><input name="zalo" value="<?= e($s['zalo']) ?>"></label>
      </div>
      <label class="f"><span>Email liên hệ</span><input type="email" name="contact_email" value="<?= e($s['contact_email']) ?>"></label>
      <label class="f"><span>Giới thiệu ở chân trang</span><textarea name="company_info" rows="3"><?= e($s['company_info']) ?></textarea></label>
      <label class="f"><span>Tuyên bố miễn trừ trách nhiệm</span><textarea name="disclaimer" rows="3"><?= e($s['disclaimer']) ?></textarea></label>
    </section>
    <section class="card stack">
      <h2 class="card__t">Câu hỏi thường gặp</h2>
      <label class="f"><span>Mỗi dòng một câu, theo dạng <code>Câu hỏi|Câu trả lời</code></span><textarea name="faq" rows="8"><?= e($s['faq']) ?></textarea></label>
    </section>
  </div>
  <div class="stack">
    <section class="card stack">
      <h2 class="card__t">Mã theo dõi</h2>
      <label class="f"><span>Chèn vào &lt;head&gt; (Google Analytics, GTM, Facebook Pixel, TikTok Pixel…)</span><textarea name="head_scripts" rows="7" class="mono"><?= e($s['head_scripts']) ?></textarea></label>
      <label class="f"><span>Chèn cuối &lt;body&gt;</span><textarea name="body_scripts" rows="4" class="mono"><?= e($s['body_scripts']) ?></textarea></label>
      <p class="hint">Khi khách gửi đăng ký thành công, website tự gửi sự kiện <code>lead_submit</code> vào dataLayer (GTM) và <code>Lead</code> cho Facebook Pixel nếu có.</p>
      <button class="b b--primary b--block" type="submit">Lưu cài đặt</button>
    </section>
  </div>
</form>
