<?php
$isLoan = $offer['type'] === 'loan';
$payload = [
    'id' => (int)$offer['id'], 'name' => $offer['name'], 'partner' => $offer['partner'], 'type' => $offer['type'],
    'logo' => $offer['logo'], 'initials' => offer_initials($offer['partner'] ?: $offer['name']),
    'min' => (int)$offer['amount_min'], 'max' => (int)$offer['amount_max'],
];
?>
<section class="detail">
  <div class="wrap">
    <nav class="crumbs" aria-label="Vị trí"><a href="/">Trang chủ</a> / <a href="/#goi"><?= e(offer_types()[$offer['type']]) ?></a></nav>
    <div class="detail__grid">
      <div>
        <div class="detail__head">
          <?php if ($offer['logo']): ?>
            <img class="logo logo--lg" src="<?= e($offer['logo']) ?>" alt="<?= e($offer['partner']) ?>" width="72" height="72">
          <?php else: ?>
            <span class="logo logo--lg" aria-hidden="true"><?= e(offer_initials($offer['partner'] ?: $offer['name'])) ?></span>
          <?php endif; ?>
          <div>
            <h1><?= e($offer['name']) ?></h1>
            <p class="offer__partner"><?= e($offer['partner']) ?><?php if ($offer['badge']): ?> <span class="tag"><?= e($offer['badge']) ?></span><?php endif; ?></p>
          </div>
        </div>

        <?php if ($offer['description']): ?><p class="detail__lead"><?= nl2br(e($offer['description'])) ?></p><?php endif; ?>

        <?php if (lines($offer['highlights'])): ?>
          <h2>Điểm nổi bật</h2>
          <ul class="ticks"><?php foreach (lines($offer['highlights']) as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>

        <?php if (lines($offer['requirements'])): ?>
          <h2>Điều kiện đăng ký</h2>
          <ul class="ticks ticks--plain"><?php foreach (lines($offer['requirements']) as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>

      <aside class="detail__card">
        <dl class="detail__facts">
          <div><dt><?= $isLoan ? 'Hạn mức' : 'Hạn mức thẻ' ?></dt><dd><?= e(money_range($offer['amount_min'], $offer['amount_max'])) ?></dd></div>
          <div><dt><?= $isLoan ? 'Lãi suất' : 'Ưu đãi lãi' ?></dt><dd><?= e($offer['rate_text'] ?: '—') ?></dd></div>
          <?php if ($isLoan): ?><div><dt>Kỳ hạn</dt><dd><?= $offer['term_max'] ? (int)$offer['term_min'] . ' – ' . (int)$offer['term_max'] . ' tháng' : '—' ?></dd></div><?php endif; ?>
          <?php if (!$isLoan && $offer['annual_fee']): ?><div><dt>Phí thường niên</dt><dd><?= e($offer['annual_fee']) ?></dd></div><?php endif; ?>
          <?php if ($offer['age_text']): ?><div><dt>Độ tuổi</dt><dd><?= e($offer['age_text']) ?></dd></div><?php endif; ?>
          <?php if ($offer['approval_text']): ?><div><dt>Thời gian duyệt</dt><dd><?= e($offer['approval_text']) ?></dd></div><?php endif; ?>
        </dl>
        <button type="button" class="btn btn--gold btn--block btn--lg" data-register='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>Đăng ký gói này</button>
        <p class="regform__note">Miễn phí đăng ký. Kết quả duyệt do đối tác quyết định.</p>
      </aside>
    </div>

    <?php if ($related): ?>
      <h2 class="detail__more">Gói tương tự</h2>
      <div class="offer-list">
        <?php foreach ($related as $o) require APP . '/views/site/_offer_row.php'; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
