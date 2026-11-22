/* Bản demo tĩnh: giả lập API Go + tracker Rust ngay trong trình duyệt.
 * Dữ liệu ban đầu lấy từ demo/fixtures.json (ghi từ API thật). Thay đổi (đăng ký mới, đổi trạng thái)
 * lưu tạm trong localStorage để landing và CMS demo dùng chung. Chỉ dùng cho bản demo. */
(function () {
  'use strict';
  var F = window.__DEMO_FIXTURES__;
  var KEY = 'genz-demo-v1';
  var S = { added: [], patch: {}, deleted: {} };
  try { var saved = JSON.parse(localStorage.getItem(KEY) || 'null'); if (saved) S = saved; } catch (e) { /* không có bộ nhớ: chạy bình thường */ }
  function persist() { try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) { /* bỏ qua */ } }

  function json(data, status) {
    return Promise.resolve(new Response(JSON.stringify(data), { status: status || 200, headers: { 'Content-Type': 'application/json' } }));
  }
  function err(msg, status, errors) { return json({ message: msg, errors: errors || {} }, status || 422); }

  function allLeads() {
    return F.leads.concat(S.added)
      .filter(function (l) { return !S.deleted[l.id]; })
      .map(function (l) { return Object.assign({}, l, S.patch[l.id] || {}); })
      .sort(function (a, b) { return b.id - a.id; });
  }
  function customersById() {
    var map = {};
    F.customers.forEach(function (c) { map[c.id] = c; });
    S.added.forEach(function (l) {
      if (!map[l.customer_id]) map[l.customer_id] = { id: l.customer_id, full_name: l.full_name, phone: l.phone, email: l.email, birth_year: l.birth_year,
        province: l.province, occupation: l.occupation, income: l.income, note: '', created_at: l.created_at, updated_at: l.created_at };
    });
    return map;
  }

  function leadList(q) {
    var type = q.get('type'), s = (q.get('q') || '').toLowerCase(), st = q.get('status'), offer = +q.get('offer'), landing = +q.get('landing'),
      src = q.get('source'), from = q.get('from'), to = q.get('to'), page = Math.max(1, +q.get('page') || 1), per = 30;
    var ofType = allLeads().filter(function (l) { return l.offer_type === type; });
    var counts = {};
    ofType.forEach(function (l) { counts[l.status] = (counts[l.status] || 0) + 1; });
    var items = ofType.filter(function (l) {
      if (s && (l.full_name + ' ' + l.phone + ' ' + l.email).toLowerCase().indexOf(s) < 0) return false;
      if (st && l.status !== st) return false;
      if (offer && l.offer_id !== offer) return false;
      if (landing && l.landing_id !== landing) return false;
      if (src && l.utm_source !== (src === '(trực tiếp)' ? '' : src)) return false;
      if (from && l.created_at.slice(0, 10) < from) return false;
      if (to && l.created_at.slice(0, 10) > to) return false;
      return true;
    });
    var sources = {};
    ofType.forEach(function (l) { if (l.utm_source) sources[l.utm_source] = 1; });
    return {
      items: items.slice((page - 1) * per, page * per), total: items.length, page: page, per: per, counts: counts,
      offers: F.offers_admin[type].map(function (o) { return { id: o.id, name: o.name }; }),
      landings: F.landings.map(function (l) { return { id: l.id, name: l.name }; }),
      sources: Object.keys(sources).sort(),
    };
  }

  function register(b) {
    var offer = null;
    ['loan', 'card'].forEach(function (t) { F.offers_admin[t].forEach(function (o) { if (o.id === b.offer_id) offer = o; }); });
    if (!offer) return err('Gói này không còn nhận đăng ký. Hãy chọn gói khác.');
    var phone = String(b.phone || '').replace(/\D/g, '').replace(/^84/, '0');
    if (!/^0[35789]\d{8}$/.test(phone)) return err('Kiểm tra lại các ô được đánh dấu.', 422, { phone: 'Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.' });
    var existing = null, cmap = customersById();
    Object.keys(cmap).forEach(function (k) { if (cmap[k].phone === phone) existing = cmap[k]; });
    var maxId = Math.max.apply(null, F.leads.concat(S.added).map(function (l) { return l.id; }).concat([0]));
    var landing = F.landings.filter(function (l) { return l.id === b.landing_id; })[0];
    var isLoan = offer.type === 'loan';
    var lead = {
      id: maxId + 1, customer_id: existing ? existing.id : 100000 + maxId, offer_id: offer.id, offer_name: offer.name, offer_type: offer.type,
      landing_id: landing ? landing.id : null, landing_name: landing ? landing.name : null,
      full_name: b.full_name, phone: phone, email: b.email || '', birth_year: b.birth_year, province: b.province, occupation: b.occupation, income: b.income,
      amount: isLoan ? b.amount : null, term: isLoan ? b.term : null, purpose: b.purpose || '', salary_method: b.salary_method || '',
      has_card: b.has_card || '', card_need: b.card_need || '', status: 'new', note: '',
      utm_source: b.utm_source || '', utm_medium: b.utm_medium || '', utm_campaign: b.utm_campaign || '', utm_content: b.utm_content || '',
      referrer: b.referrer || '', ip: '(bản demo)', user_agent: navigator.userAgent, clicked_out_at: null, created_at: new Date().toISOString(),
    };
    S.added.push(lead);
    persist();
    return json({ ok: true, id: lead.id, redirect: '', offer: offer.name, type: offer.type });
  }

  function route(method, url, body) {
    var u = new URL(url, location.href), p = u.pathname.replace(/^.*?(\/api\/)/, '/api/'), q = u.searchParams, m;
    // ---- công khai ----
    if (p === '/api/public/site') return json(F.site);
    if (p === '/api/public/options' || p === '/api/admin/options') return json(F.options);
    if (p === '/api/public/landing') {
      var d = F.landing[q.get('slug') || ''];
      return d ? json(d) : err('Không tìm thấy trang này.', 404);
    }
    if ((m = p.match(/^\/api\/public\/module\/(loan|card)$/))) return json(F.module[m[1]]);
    if ((m = p.match(/^\/api\/public\/offers\/([^/]+)$/))) return F.offer_public[m[1]] ? json(F.offer_public[m[1]]) : err('Gói này không còn nhận đăng ký.', 404);
    if (p === '/api/public/register') return register(body || {});
    // ---- quản trị ----
    if (p === '/api/admin/setup') return json({ needs_setup: false });
    if (p === '/api/admin/login' || p === '/api/admin/logout') return json({ ok: true });
    if (p === '/api/admin/me') return method === 'GET' ? json(F.me) : json({ ok: true });
    if (p === '/api/admin/dashboard') return json(F.dashboard[q.get('range') || '30'] || F.dashboard['30']);
    if (p === '/api/admin/leads') return json(leadList(q));
    if (p === '/api/admin/leads/bulk') {
      (body.ids || []).forEach(function (id) {
        if (body.action === 'delete') S.deleted[id] = 1; else S.patch[id] = Object.assign(S.patch[id] || {}, { status: body.action });
      });
      persist();
      return json({ ok: true, affected: (body.ids || []).length });
    }
    if ((m = p.match(/^\/api\/admin\/leads\/(\d+)$/))) {
      var id = +m[1];
      if (method === 'PATCH') {
        S.patch[id] = Object.assign(S.patch[id] || {}, body.status ? { status: body.status } : {}, body.note != null ? { note: body.note } : {});
        persist();
        return json({ ok: true });
      }
      var all = allLeads(), lead = all.filter(function (l) { return l.id === id; })[0];
      if (!lead) return err('Không tìm thấy đăng ký này.', 404);
      return json({ lead: lead, others: all.filter(function (l) { return l.customer_id === lead.customer_id && l.id !== id; }) });
    }
    if (p === '/api/admin/customers') {
      var s = (q.get('q') || '').toLowerCase(), page = Math.max(1, +q.get('page') || 1), cmap = customersById(), leads = allLeads();
      var list = Object.keys(cmap).map(function (k) {
        var c = cmap[k], mine = leads.filter(function (l) { return l.customer_id === c.id; });
        return { id: c.id, full_name: c.full_name, phone: c.phone, province: c.province,
          loans: mine.filter(function (l) { return l.offer_type === 'loan'; }).length, cards: mine.filter(function (l) { return l.offer_type === 'card'; }).length,
          last_reg: mine.length ? mine[0].created_at : null };
      }).filter(function (c) { return !s || (c.full_name + ' ' + c.phone).toLowerCase().indexOf(s) >= 0; })
        .sort(function (a, b) { return (b.last_reg || '').localeCompare(a.last_reg || ''); });
      return json({ items: list.slice((page - 1) * 30, page * 30), total: list.length, page: page, per: 30 });
    }
    if ((m = p.match(/^\/api\/admin\/customers\/(\d+)$/))) {
      var c = customersById()[+m[1]];
      if (!c) return err('Không tìm thấy khách hàng.', 404);
      if (method !== 'GET') return json({ ok: true });
      return json({ customer: c, leads: allLeads().filter(function (l) { return l.customer_id === c.id; }) });
    }
    if (p === '/api/admin/offers') return method === 'GET' ? json({ items: F.offers_admin[q.get('type')] || [] }) : json({ ok: true, id: 1, type: body.type });
    if ((m = p.match(/^\/api\/admin\/offers\/(\d+)$/))) {
      if (method !== 'GET') return json({ ok: true, id: +m[1] });
      var o = F.offers_admin.loan.concat(F.offers_admin.card).filter(function (x) { return x.id === +m[1]; })[0];
      return o ? json(o) : err('Không tìm thấy gói này.', 404);
    }
    if (p === '/api/admin/uploads') return err('Bản demo không lưu ảnh tải lên.');
    if (p === '/api/admin/landings') return method === 'GET' ? json({ items: F.landings }) : json({ ok: true, id: 1 });
    if ((m = p.match(/^\/api\/admin\/landings\/(\d+)$/))) {
      if (method !== 'GET') return json({ ok: true, id: +m[1] });
      var l = F.landings.filter(function (x) { return x.id === +m[1]; })[0];
      return l ? json(l) : err('Không tìm thấy landing này.', 404);
    }
    if (p === '/api/admin/users' || /^\/api\/admin\/users\/\d+$/.test(p)) return method === 'GET' ? json(F.users) : json({ ok: true, id: 1 });
    if (p === '/api/admin/settings') return method === 'GET' ? json(F.settings) : json({ ok: true });
    return err('Bản demo chưa giả lập đường dẫn này.', 404);
  }

  var realFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : input.url;
    var method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
    if (/(^|\/)api\/(public|admin)\//.test(url)) {
      var body = null;
      try { body = init && typeof init.body === 'string' ? JSON.parse(init.body) : null; } catch (e) { /* không phải JSON */ }
      return route(method, url, body);
    }
    return realFetch(input, init);
  };
  // Tracker Rust: lượt xem không cần ghi trong bản demo
  try { navigator.sendBeacon = function () { return true; }; } catch (e) { /* bỏ qua */ }

  // Thông báo nhỏ cho các thao tác không có trong bản demo
  function say(text) {
    var t = document.getElementById('demo-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'demo-toast';
      t.setAttribute('role', 'status');
      t.style.cssText = 'position:fixed;z-index:2147483646;left:50%;transform:translateX(-50%);top:calc(16px + env(safe-area-inset-top,0px));background:#1a1033;color:#fff;padding:12px 18px;border-radius:12px;font:600 14px "Be Vietnam Pro",system-ui,sans-serif;max-width:calc(100% - 32px);box-shadow:0 10px 30px -8px rgba(0,0,0,.45)';
      document.body.appendChild(t);
    }
    t.textContent = text;
    t.hidden = false;
    clearTimeout(say.t);
    say.t = setTimeout(function () { t.hidden = true; }, 3200);
  }
  window.__demoSay = say;

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href');
    if (href.indexOf('/api/admin/leads/export') >= 0) { e.preventDefault(); say('Bản thật tải về file CSV mở bằng Excel, cột riêng cho vay hoặc thẻ.'); return; }
    if (/^(tel:|mailto:|https:\/\/zalo\.me)/.test(href)) { e.preventDefault(); say('Bản demo: nút này gọi điện hoặc mở Zalo trên website thật.'); return; }
    // Neo trong trang (#khoan-vay…): cuộn tới, không đổi route
    if (href.charAt(0) === '#' && href.charAt(1) !== '/') {
      var el = document.getElementById(href.slice(1));
      if (el) { e.preventDefault(); e.stopPropagation(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
  }, true);

  // Thanh chuyển nhanh giữa landing và CMS trong bản demo
  function bar() {
    if (document.getElementById('demo-bar')) return;
    var admin = window.__DEMO_APP__ === 'admin';
    var b = document.createElement('nav');
    b.id = 'demo-bar';
    b.setAttribute('aria-label', 'Chuyển trang demo');
    b.style.cssText = 'position:fixed;z-index:2147483645;right:max(12px,env(safe-area-inset-right,0px));bottom:calc(12px + env(safe-area-inset-bottom,0px));display:flex;gap:4px;align-items:center;background:#1a1033;color:#fff;border-radius:999px;padding:5px 6px 5px 14px;font:700 13px/1.2 "Be Vietnam Pro",system-ui,sans-serif;box-shadow:0 10px 30px -8px rgba(0,0,0,.45)';
    var link = function (href, text, hot) {
      return '<a href="' + href + '" style="text-decoration:none;padding:8px 12px;border-radius:999px;' + (hot ? 'background:#ffd23f;color:#1a1033' : 'color:#fff;background:rgba(255,255,255,.1)') + '">' + text + '</a>';
    };
    b.innerHTML = '<span style="color:#ffd23f;margin-right:4px">Demo</span>' +
      (admin ? link('../index.html', 'Xem landing', true) : link('admin/index.html', 'Mở CMS quản trị', true));
    document.body.appendChild(b);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bar); else bar();
})();
