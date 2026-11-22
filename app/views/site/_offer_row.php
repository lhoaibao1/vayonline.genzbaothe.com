<?php
/** @var array $o */
$isLoan = $o['type'] === 'loan';
$range = money_range($o['amount_min'], $o['amount_max']);
$payload = [
    'id' => (int)$o['id'], 'name' => $o['name'], 'partner' => $o['partner'], 'type' => $o['type'],
    'logo' => $o['logo'], 'initials' => offer_initials($o['partner'] ?: $o['name']),
    'min' => (int)$o['amount_min'], 'max' => (int)$o['amount_max'],
];
?>
<article class="offer" data-type="<?= e($o['type']) ?>" data-min="<?= (int)$o['amount_min'] ?>" data-max="<?= (int)$o['amount_max'] ?>"
  data-tmin="<?= (int)$o['term_min'] ?>" data-tmax="<?= (int)$o['term_max'] ?>" data-rate="<?= (float)$o['rate_min'] ?>">
  <div class="offer__id">
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

  <dl class="offer__facts">
    <div><dt><?= $isLoan ? 'Hạn mức' : 'Hạn mức thẻ' ?></dt><dd><?= e($range) ?></dd></div>
    <div><dt><?= $isLoan ? 'Lãi suất' : 'Ưu đãi lãi' ?></dt><dd><?= e($o['rate_text'] ?: '—') ?></dd></div>
    <?php if ($isLoan): ?>
      <div><dt>Kỳ hạn</dt><dd><?= $o['term_max'] ? (int)$o['term_min'] . ' – ' . (int)$o['term_max'] . ' tháng' : '—' ?></dd></div>
    <?php else: ?>
      <div><dt>Phí thường niên</dt><dd><?= e($o['annual_fee'] ?: '—') ?></dd></div>
    <?php endif; ?>
    <div><dt>Thời gian duyệt</dt><dd><?= e($o['approval_text'] ?: '—') ?></dd></div>
  </dl>

  <div class="offer__act">
    <button type="button" class="btn btn--gold" data-register='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>Đăng ký</button>
    <a class="offer__more" href="/uu-dai/<?= e($o['slug']) ?>">Điều kiện &amp; chi tiết</a>
  </div>
  <p class="offer__fit" hidden></p>
</article>
