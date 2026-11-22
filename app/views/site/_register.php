<dialog class="sheet" id="dang-ky" aria-labelledby="dk-title">
  <div class="sheet__body">
    <button type="button" class="sheet__close" data-close aria-label="Đóng">×</button>

    <form class="regform" id="regform" novalidate>
      <div class="regform__offer">
        <span class="logo logo--sm" data-r-logo></span>
        <div>
          <p class="regform__eyebrow" data-r-type>Đăng ký</p>
          <h2 id="dk-title" data-r-name>Đăng ký tư vấn</h2>
        </div>
      </div>

      <input type="hidden" name="offer_id" value="">
      <input type="hidden" name="landing_id" value="<?= (int)($landing['id'] ?? 0) ?>">
      <input type="hidden" name="term" value="">
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

      <label class="field">
        <span>Họ và tên</span>
        <input name="full_name" autocomplete="name" required maxlength="80" placeholder="Như trên CCCD">
        <small class="field__err" data-err="full_name"></small>
      </label>
      <label class="field">
        <span>Số điện thoại</span>
        <input name="phone" type="tel" inputmode="numeric" autocomplete="tel" required maxlength="12" placeholder="09xx xxx xxx">
        <small class="field__err" data-err="phone"></small>
      </label>
      <div class="field-row">
        <label class="field">
          <span>Tỉnh / thành phố</span>
          <select name="province">
            <option value="">Chọn nơi ở</option>
            <?php foreach (provinces() as $p): ?><option><?= e($p) ?></option><?php endforeach; ?>
          </select>
          <small class="field__err" data-err="province"></small>
        </label>
        <label class="field">
          <span>Thu nhập mỗi tháng</span>
          <select name="income">
            <option value="">Chọn mức</option>
            <?php foreach (income_options() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="field" data-r-amount-field>
        <span>Số tiền muốn vay</span>
        <input name="amount" inputmode="numeric" placeholder="Ví dụ: 30.000.000" data-money>
      </label>

      <label class="check">
        <input type="checkbox" name="consent" value="1" checked>
        <span>Tôi đồng ý để đối tác và tư vấn viên liên hệ qua số điện thoại trên để hướng dẫn hồ sơ.</span>
      </label>
      <small class="field__err" data-err="consent"></small>
      <small class="field__err" data-err="offer_id"></small>
      <p class="form-msg" role="alert" data-msg></p>

      <button class="btn btn--gold btn--block" type="submit">Gửi đăng ký</button>
      <p class="regform__note">Miễn phí. Không yêu cầu chuyển tiền trước dưới bất kỳ hình thức nào.</p>
    </form>

    <div class="done" id="regdone" hidden>
      <div class="done__tick" aria-hidden="true"></div>
      <h2>Đã nhận đăng ký</h2>
      <p data-done-text>Tư vấn viên sẽ gọi cho bạn trong giờ hành chính.</p>
      <a class="btn btn--gold btn--block" href="#" data-done-go hidden>Tiếp tục hồ sơ tại trang đối tác</a>
      <p class="done__count" data-done-count></p>
      <button type="button" class="btn btn--ghost btn--block" data-close>Xem thêm gói khác</button>
    </div>
  </div>
</dialog>
