"""Gom bản demo tĩnh vào một thư mục để đăng lên link xem thử.

  landing: web/.output/public (DEMO=1 npx nuxi generate)
  CMS:     admin/dist          (DEMO=1 npx vite build)

Kết quả: <out>/index.html (landing) + <out>/nuxt/… + <out>/admin/index.html (CMS) + <out>/admin/assets/…
Mọi đường dẫn đổi sang tương đối để chạy được dưới bất kỳ thư mục nào.
"""
import os
import re
import shutil
import sys

root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
out = sys.argv[1]
shutil.rmtree(out, ignore_errors=True)
shutil.copytree(os.path.join(root, 'web/.output/public'), out)
for junk in ('200.html', '404.html'):
    if os.path.exists(os.path.join(out, junk)):
        os.remove(os.path.join(out, junk))
shutil.copytree(os.path.join(root, 'admin/dist'), os.path.join(out, 'admin'))

# Landing: tài nguyên /nuxt/ -> ./nuxt/, router dùng đường dẫn hiện tại làm gốc
p = os.path.join(out, 'index.html')
html = open(p, encoding='utf-8').read()
html = html.replace('"/nuxt/', '"./nuxt/')
html = html.replace('app:{baseURL:"/"', 'app:{baseURL:""').replace('cdnURL:""', 'cdnURL:"./"')
open(p, 'w', encoding='utf-8').write(html)
print('demo:', out)

# --artifact: trang chính được nền tảng bọc sẵn <html>/<head>/<body>, nên chỉ giữ phần bên trong
if '--artifact' in sys.argv:
    head = re.search(r'<head>(.*?)</head>', html, re.S).group(1)
    head = re.sub(r'<meta charset[^>]*>\s*|<meta name="viewport"[^>]*>\s*', '', head)
    body = re.search(r'<body[^>]*>(.*)</body>', html, re.S).group(1)
    open(p, 'w', encoding='utf-8').write('<title>GenZbaothe.com Demo</title>\n' + head + body)
