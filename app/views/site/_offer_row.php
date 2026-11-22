<?php
/** @var array $o */
$isLoan = $o['type'] === 'loan';
$payload = [
    'id' => (int)$o['id'], 'name' => $o['name'], 'partner' => $o['partner'], 'type' => $o['type'],
    'logo' => $o['logo'], 'initials' => offer_initials($o['partner'] ?: $o['name']),
    'min' => (int)$o['amount_min'], 'max' => (int)$o['amount_max'],
];
?>
<article class="offer" data-type="<?= e($o['type']) ?>" data-min="<?= (int)$o['amount_min'] ?>" data-max="<?= (int)$o['amount_max'] ?>"
  data-tmin="<?= (int)$o['term_min'] ?>" data-tmax="<?= (int)$o['term_max'] ?>" data-rate="<?= (float)$o['rate_min'] ?>">
  <div class="offer__top">
    <?php if ($o['logo']): ?>
      <img class="logo" src="<?= e($o['logo']) ?>" alt="<?= e($o['partner'] ?: $o['name']) ?>" width="56" height="56" loading="lazy">
    <?php else: ?>
      <span class="logo" aria-hidden="true"><?= e(offer_initials($o['partner'] ?: $o['name'])) ?></span>
    <?php endif; ?>
    <div>
      <h3 class="offer__name"><a href="/uu-dai/<?= e($o['slug']) ?>"><?= e($o['name']) ?></a></h3>
      <p class="offer__partner"><?= e($o['partner']) ?><?php if ($o['badge']): ?> <span class="tag"><?= e($o['badge']) ?></span><?php endif; ?></p>
    </div>
  </div>

  <div class="offer__body">
    <p class="offer__big">
      <small><?= $isLoan ? 'Vay tối đa' : 'Hạn mức thẻ đến' ?></small>
      <strong><?= $o['amount_max'] ? e(money_vnd($o['amount_max'])) : 'Theo hồ sơ' ?></strong>
    </p>
    <dl class="offer__facts">
      <div><dt><?= $isLoan ? 'Lãi suất' : 'Ưu đãi lãi' ?></dt><dd><?= e($o['rate_text'] ?: '—') ?></dd></div>
      <div><dt>Duyệt trong</dt><dd><?= e($o['approval_text'] ?: '—') ?></dd></div>
      <?php if ($isLoan): ?>
        <div><dt>Kỳ hạn</dt><dd><?= $o['term_max'] ? (int)$o['term_min'] . ' – ' . (int)$o['term_max'] . ' tháng' : '—' ?></dd></div>
        <div><dt>Từ</dt><dd><?= e(money_vnd($o['amount_min'])) ?></dd></div>
      <?php else: ?>
        <div><dt>Phí năm</dt><dd><?= e($o['annual_fee'] ?: '—') ?></dd></div>
        <div><dt>Độ tuổi</dt><dd><?= e($o['age_text'] ?: '—') ?></dd></div>
      <?php endif; ?>
    </dl>
  </div>
  <p class="offer__fit" hidden></p>

  <div class="offer__act">
    <button type="button" class="btn btn--gold btn--block" data-register='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>Đăng ký ngay</button>
    <a class="offer__more" href="/uu-dai/<?= e($o['slug']) ?>">Xem điều kiện</a>
  </div>
</article>
