<?php
$types = array_unique(array_column($offers, 'type'));
$hasLoan = in_array('loan', $types, true);
$hasCard = in_array('card', $types, true);
$loans = array_filter($offers, fn($o) => $o['type'] === 'loan');
$maxAmount = $loans ? max(array_column($loans, 'amount_max')) : 0;
$minAmount = $loans ? min(array_column($loans, 'amount_min')) : 0;
$showCalc = $landing['show_calculator'] && $hasLoan && $maxAmount > 0;
$startAmount = $showCalc ? min($maxAmount, max($minAmount, 30_000_000)) : 0;
?>
<section class="hero">
  <div class="wrap hero__in <?= $showCalc ? '' : 'hero__in--solo' ?>">
    <div class="hero__copy">
      <h1><?= e($landing['headline']) ?></h1>
      <?php if ($landing['subheadline']): ?><p class="hero__sub"><?= e($landing['subheadline']) ?></p><?php endif; ?>
      <ul class="hero__facts">
        <li><strong><?= count($offers) ?> gói</strong> từ đối tác được cấp phép</li>
        <li><strong>2 phút</strong> điền thông tin</li>
        <li><strong>0đ</strong> phí đăng ký</li>
      </ul>
      <?php if (!$showCalc): ?><a class="btn btn--gold btn--lg" href="#goi">Xem và so sánh các gói</a><?php endif; ?>
    </div>

    <?php if ($showCalc): ?>
    <form class="calc" id="calc" aria-label="Tính khoản vay" onsubmit="return false">
      <label class="calc__label" for="calc-amount">Bạn cần bao nhiêu?</label>
      <output class="calc__amount" id="calc-amount-out" for="calc-amount"><?= number_format($startAmount, 0, ',', '.') ?><span>đ</span></output>
      <input class="calc__range" id="calc-amount" type="range" name="amount"
        min="<?= max(1_000_000, (int)$minAmount) ?>" max="<?= (int)$maxAmount ?>" step="1000000" value="<?= (int)$startAmount ?>">
      <div class="calc__scale"><span><?= e(money_vnd(max(1_000_000, $minAmount))) ?></span><span><?= e(money_vnd($maxAmount)) ?></span></div>

      <fieldset class="calc__terms">
        <legend>Trả trong</legend>
        <?php foreach ([6, 12, 24, 36] as $t): ?>
          <label><input type="radio" name="term" value="<?= $t ?>" <?= $t === 12 ? 'checked' : '' ?>><span><?= $t ?> tháng</span></label>
        <?php endforeach; ?>
      </fieldset>

      <div class="calc__result" aria-live="polite">
        <p>Trả mỗi tháng từ khoảng</p>
        <p class="calc__monthly" id="calc-monthly">—</p>
        <p class="calc__hint" id="calc-hint">Ước tính theo lãi suất thấp nhất trong các gói phù hợp.</p>
      </div>
      <a class="btn btn--gold btn--block btn--lg" href="#goi" id="calc-cta">Xem gói phù hợp</a>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="offers" id="goi">
  <div class="wrap">
    <div class="offers__head">
      <h2>So sánh các gói</h2>
      <?php if ($hasLoan && $hasCard): ?>
      <div class="tabs" role="tablist" aria-label="Lọc theo loại">
        <button role="tab" aria-selected="true" data-filter="all">Tất cả</button>
        <button role="tab" aria-selected="false" data-filter="loan">Khoản vay</button>
        <button role="tab" aria-selected="false" data-filter="card">Thẻ tín dụng</button>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$offers): ?>
      <p class="empty">Hiện chưa có gói nào nhận đăng ký. Hãy quay lại sau.</p>
    <?php else: ?>
      <div class="offer-list" id="offer-list">
        <?php foreach ($offers as $o) require APP . '/views/site/_offer_row.php'; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="steps" id="cach-dang-ky">
  <div class="wrap">
    <h2>Đăng ký diễn ra thế nào</h2>
    <ol class="steps__list">
      <li><h3>Chọn gói</h3><p>So sánh hạn mức, lãi suất và thời gian duyệt. Bấm “Đăng ký” ở gói bạn thấy hợp.</p></li>
      <li><h3>Để lại thông tin</h3><p>Họ tên và số điện thoại là đủ. Bạn được chuyển sang trang đối tác để hoàn tất hồ sơ.</p></li>
      <li><h3>Nhận kết quả</h3><p>Đối tác thẩm định và báo kết quả qua điện thoại hoặc tin nhắn. Tiền giải ngân thẳng vào tài khoản của bạn.</p></li>
    </ol>
    <p class="steps__warn">Lưu ý: không đối tác nào yêu cầu bạn chuyển tiền trước để “mở khoá” hay “chứng minh tài chính”. Nếu gặp yêu cầu như vậy, hãy dừng lại và báo cho chúng tôi.</p>
  </div>
</section>

<?php if ($faq): ?>
<section class="faq" id="hoi-dap">
  <div class="wrap faq__in">
    <h2>Câu hỏi thường gặp</h2>
    <div class="faq__list">
      <?php foreach ($faq as [$qq, $aa]): ?>
        <details><summary><?= e($qq) ?></summary><p><?= e($aa) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
