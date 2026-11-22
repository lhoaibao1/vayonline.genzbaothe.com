"""Ghi dữ liệu mẫu từ API Go đang chạy (SEED_DEMO=1) thành demo/fixtures.json cho bản demo tĩnh.

Cách dùng: python3 demo/record.py http://127.0.0.1:8080 demo@genzbaothe.com demo12345
"""
import http.cookiejar
import json
import sys
import urllib.parse
import urllib.request

base, email, password = sys.argv[1], sys.argv[2], sys.argv[3]
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def call(path, method="GET", body=None):
    req = urllib.request.Request(base + path, method=method, data=json.dumps(body).encode() if body else None,
                                 headers={"X-Requested-With": "XMLHttpRequest", "Content-Type": "application/json"})
    with opener.open(req) as r:
        return json.loads(r.read())


call("/api/admin/login", "POST", {"email": email, "password": password})

fx = {
    "site": call("/api/public/site"),
    "options": call("/api/public/options"),
    "module": {t: call(f"/api/public/module/{t}") for t in ("loan", "card")},
    "me": call("/api/admin/me"),
    "dashboard": {str(r): call(f"/api/admin/dashboard?range={r}") for r in (7, 30, 90)},
    "landings": call("/api/admin/landings")["items"],
    "users": call("/api/admin/users"),
    "settings": call("/api/admin/settings"),
}
fx["landing"] = {"": call("/api/public/landing")}
for l in fx["landings"]:
    if l["active"]:
        fx["landing"][l["slug"]] = call("/api/public/landing?slug=" + urllib.parse.quote(l["slug"]))

offers = {}
fx["offers_admin"] = {}
for t in ("loan", "card"):
    items = call(f"/api/admin/offers?type={t}")["items"]
    fx["offers_admin"][t] = items
    for o in items:
        offers[o["id"]] = o
fx["offer_public"] = {}
for o in offers.values():
    if o["active"]:
        fx["offer_public"][o["slug"]] = call(f"/api/public/offers/{o['slug']}")

# Toàn bộ đăng ký (lấy hết các trang) và hồ sơ khách hàng
leads = []
for t in ("loan", "card"):
    page = 1
    while True:
        d = call(f"/api/admin/leads?type={t}&page={page}")
        leads += d["items"]
        if page * d["per"] >= d["total"]:
            break
        page += 1
fx["leads"] = leads
fx["customers"] = [call(f"/api/admin/customers/{cid}")["customer"] for cid in sorted({l["customer_id"] for l in leads})]

fx["pages_admin"] = call("/api/admin/pages")["items"]
fx["pages"] = {p["slug"]: call("/api/public/pages/" + p["slug"]) for p in fx["pages_admin"]}
fx["data_requests"] = call("/api/admin/data-requests")

json.dump(fx, open("demo/fixtures.json", "w"), ensure_ascii=False, separators=(",", ":"))
print("ghi xong:", len(leads), "đăng ký,", len(fx["customers"]), "khách hàng")
