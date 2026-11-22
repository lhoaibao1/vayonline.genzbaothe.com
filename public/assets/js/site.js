(function () {
  'use strict';
  var fmt = function (n) { return Math.round(n).toLocaleString('vi-VN'); };
  var short = function (n) {
    if (n >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ';
    if (n >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' triệu';
    return fmt(n) + 'đ';
  };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  var list = $('#offer-list');
  var rows = $$('.offer', list || document);
  var state = { amount: 0, term: 0, filter: 'all' };

  /* ---------- Máy tính khoản vay ---------- */
  var calc = $('#calc');
  function monthly(p, yearRate, n) {
    var i = yearRate / 100 / 12;
    if (!i) return p / n;
    return p * i / (1 - Math.pow(1 + i, -n));
  }
  function updateCalc() {
    var range = $('#calc-amount');
    state.amount = +range.value;
    state.term = +($('input[name=term]:checked', calc) || {}).value || 12;
    var pct = (range.value - range.min) / (range.max - range.min) * 100;
    range.style.setProperty('--p', pct + '%');
    $('#calc-amount-out').innerHTML = fmt(state.amount) + '<span>đ</span>';

    var best = null, fitCount = 0;
    rows.forEach(function (r) {
      if (r.dataset.type !== 'loan') return;
      var min = +r.dataset.min, max = +r.dataset.max, tmin = +r.dataset.tmin, tmax = +r.dataset.tmax, rate = +r.dataset.rate;
      var fitAmount = (!max || (state.amount >= min && state.amount <= max));
      var fitTerm = (!tmax || (state.term >= tmin && state.term <= tmax));
      var fit = fitAmount && fitTerm;
      var note = $('.offer__fit', r);
      r.classList.toggle('is-out', !fit);
      r.classList.toggle('is-fit', fit);
      note.hidden = false;
      if (fit) {
        fitCount++;
        if (rate > 0 && (best === null || rate < best)) best = rate;
        note.textContent = rate > 0
          ? 'Phù hợp: vay ' + short(state.amount) + ' trong ' + state.term + ' tháng, trả khoảng ' + fmt(monthly(state.amount, rate, state.term)) + 'đ/tháng'
          : 'Phù hợp với số tiền và kỳ hạn bạn chọn';
      } else {
        note.textContent = !fitAmount
          ? 'Hạn mức gói này: ' + short(min) + ' – ' + short(max)
          : 'Kỳ hạn gói này: ' + tmin + ' – ' + tmax + ' tháng';
      }
    });
    // Đưa gói phù hợp lên đầu
    if (list) {
      rows.slice().sort(function (a, b) {
        return (a.classList.contains('is-out') ? 1 : 0) - (b.classList.contains('is-out') ? 1 : 0);
      }).forEach(function (r) { list.appendChild(r); });
    }
    $('#calc-monthly').textContent = best !== null ? fmt(monthly(state.amount, best, state.term)) + 'đ' : '—';
    $('#calc-hint').textContent = fitCount
      ? 'Ước tính theo lãi suất thấp nhất trong ' + fitCount + ' gói phù hợp. Số thực tế do đối tác báo.'
      : 'Chưa có gói khớp số tiền và kỳ hạn này. Thử giảm số tiền hoặc đổi kỳ hạn.';
    $('#calc-cta').textContent = fitCount ? 'Xem ' + fitCount + ' gói phù hợp' : 'Xem tất cả các gói';
  }
  if (calc) {
    calc.addEventListener('input', updateCalc);
    calc.addEventListener('change', updateCalc);
    updateCalc();
  }

  /* ---------- Tab lọc ---------- */
  $$('.tabs [data-filter]').forEach(function (b) {
    b.addEventListener('click', function () {
      state.filter = b.dataset.filter;
      $$('.tabs [data-filter]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      rows.forEach(function (r) { r.hidden = state.filter !== 'all' && r.dataset.type !== state.filter; });
    });
  });

  // Chip ở hero: chuyển tab rồi cuộn tới danh sách
  $$('[data-go-filter]').forEach(function (a) {
    a.addEventListener('click', function () {
      var t = $('.tabs [data-filter="' + a.dataset.goFilter + '"]');
      if (t) t.click();
    });
  });

  /* ---------- Hộp đăng ký ---------- */
  var dlg = $('#dang-ky');
  var form = $('#regform');
  var done = $('#regdone');
  var timer = null;
  if (!dlg || !form) return;

  function open(offer) {
    form.hidden = false; done.hidden = true;
    clearErrors();
    form.offer_id.value = offer.id;
    $('[data-r-name]', form).textContent = offer.name;
    $('[data-r-type]', form).textContent = (offer.type === 'card' ? 'Mở thẻ tín dụng' : 'Đăng ký khoản vay') + (offer.partner ? ' · ' + offer.partner : '');
    var logo = $('[data-r-logo]', form), el;
    if (offer.logo) { el = new Image(); el.src = offer.logo; el.alt = ''; }
    else { el = document.createElement('span'); el.textContent = offer.initials; }
    el.className = 'logo logo--sm'; el.setAttribute('data-r-logo', '');
    logo.replaceWith(el);
    var amountField = $('[data-r-amount-field]', form);
    amountField.hidden = offer.type === 'card';
    if (offer.type !== 'card') {
      var a = state.amount || '';
      if (a && offer.max) a = Math.min(Math.max(a, offer.min), offer.max);
      form.amount.value = a ? fmt(a) : '';
      form.term.value = state.term || '';
    } else { form.amount.value = ''; form.term.value = ''; }
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    setTimeout(function () { form.full_name.focus(); }, 50);
  }
  function close() { clearInterval(timer); if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-register]');
    if (t) { ev.preventDefault(); open(JSON.parse(t.getAttribute('data-register'))); return; }
    if (ev.target.closest('[data-close]')) close();
  });
  dlg.addEventListener('click', function (ev) { if (ev.target === dlg) close(); });

  $$('[data-money]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var d = inp.value.replace(/\D/g, '');
      inp.value = d ? fmt(+d) : '';
    });
  });

  function clearErrors() {
    $$('[data-err]', form).forEach(function (e) { e.textContent = ''; });
    $$('.has-err', form).forEach(function (e) { e.classList.remove('has-err'); });
    $('[data-msg]', form).textContent = '';
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearErrors();
    var phone = form.phone.value.replace(/\D/g, '').replace(/^84/, '0');
    var bad = false;
    if (form.full_name.value.trim().length < 2) { showErr('full_name', 'Nhập họ tên như trên CCCD.'); bad = true; }
    if (!/^0[35789]\d{8}$/.test(phone)) { showErr('phone', 'Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.'); bad = true; }
    if (!form.consent.checked) { showErr('consent', 'Bạn cần đồng ý để đối tác liên hệ tư vấn.'); bad = true; }
    if (bad) return;

    var btn = $('button[type=submit]', form);
    btn.disabled = true; btn.textContent = 'Đang gửi…';
    var fd = new FormData(form);
    fd.append('_csrf', window.APP.csrf);
    fetch('/dang-ky', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Phiên làm việc đã hết hạn. Hãy tải lại trang.' }; }); })
      .then(function (res) {
        btn.disabled = false; btn.textContent = 'Gửi đăng ký';
        if (!res.ok) {
          if (res.errors) Object.keys(res.errors).forEach(function (k) { showErr(k, res.errors[k]); });
          if (res.message) $('[data-msg]', form).textContent = res.message;
          return;
        }
        if (window.dataLayer) window.dataLayer.push({ event: 'lead_submit', offer: res.offer });
        if (window.fbq) window.fbq('track', 'Lead');
        showDone(res);
      })
      .catch(function () {
        btn.disabled = false; btn.textContent = 'Gửi đăng ký';
        $('[data-msg]', form).textContent = 'Không gửi được do mất kết nối. Kiểm tra mạng rồi bấm gửi lại.';
      });
  });

  function showErr(k, msg) {
    var el = $('[data-err="' + k + '"]', form);
    if (el) el.textContent = msg;
    var input = form.elements[k];
    if (input && input.closest) { var f = input.closest('.field'); if (f) f.classList.add('has-err'); }
  }

  function showDone(res) {
    form.hidden = true; done.hidden = false;
    var go = $('[data-done-go]', done), count = $('[data-done-count]', done);
    if (res.redirect) {
      go.hidden = false; go.href = res.redirect;
      $('[data-done-text]', done).textContent = 'Bước tiếp theo: hoàn tất hồ sơ “' + res.offer + '” tại trang đối tác để được duyệt nhanh hơn.';
      var s = 5;
      count.textContent = 'Tự động chuyển sau ' + s + ' giây';
      clearInterval(timer);
      timer = setInterval(function () {
        s--;
        if (s <= 0) { clearInterval(timer); window.location.href = res.redirect; return; }
        count.textContent = 'Tự động chuyển sau ' + s + ' giây';
      }, 1000);
    } else {
      go.hidden = true; count.textContent = '';
      $('[data-done-text]', done).textContent = 'Tư vấn viên sẽ gọi cho bạn để hướng dẫn hồ sơ “' + res.offer + '”.';
    }
    go.focus();
  }
})();
