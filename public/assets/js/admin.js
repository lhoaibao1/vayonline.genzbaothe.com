(function () {
  'use strict';
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Xác nhận trước khi xoá
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  // Định dạng số tiền
  $$('[data-money]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var d = inp.value.replace(/\D/g, '');
      inp.value = d ? (+d).toLocaleString('vi-VN') : '';
    });
  });

  // Ẩn/hiện trường theo loại gói
  var typeSel = document.querySelector('[data-type-select]');
  function syncType() {
    var t = typeSel.value;
    $$('[data-loan-only]').forEach(function (el) { el.hidden = t !== 'loan'; });
    $$('[data-card-only]').forEach(function (el) { el.hidden = t !== 'card'; });
  }
  if (typeSel) { typeSel.addEventListener('change', syncType); syncType(); }

  // Đổi trạng thái nhanh trong bảng
  $$('select[data-status]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var fd = new FormData();
      fd.append('_csrf', window.ADM.csrf);
      fd.append('id', sel.getAttribute('data-status'));
      fd.append('status', sel.value);
      sel.disabled = true;
      fetch('/admin/leads/status', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
        .then(function () { sel.className = 'st-select st--' + sel.value; })
        .catch(function () { alert('Không lưu được trạng thái. Hãy tải lại trang rồi thử lại.'); })
        .finally(function () { sel.disabled = false; });
    });
  });

  // Chọn nhiều
  var all = document.querySelector('[data-check-all]');
  var bulk = document.querySelector('[data-bulk]');
  function syncBulk() {
    var n = $$('input[name="ids[]"]:checked').length;
    if (bulk) { bulk.hidden = n === 0; bulk.querySelector('[data-bulk-count]').textContent = 'Đã chọn ' + n; }
  }
  if (all) all.addEventListener('change', function () { $$('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); syncBulk(); });
  $$('input[name="ids[]"]').forEach(function (c) { c.addEventListener('change', syncBulk); });
  var bulkForm = document.getElementById('bulk-form');
  if (bulkForm) bulkForm.addEventListener('submit', function (e) {
    var a = bulkForm.querySelector('select[name=action]').value;
    if (a === 'delete' && !confirm('Xoá các đăng ký đã chọn? Không thể hoàn tác.')) e.preventDefault();
  });
})();
