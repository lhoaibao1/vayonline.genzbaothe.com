#!/usr/bin/env bash
# GenZbaothe.com: cài đặt toàn bộ lên VPS bằng một lệnh (Ubuntu 22.04/24.04, Debian 12).
#
# Tự làm: cài Docker + Git + tường lửa, tạo swap nếu thiếu RAM, tải code, sinh mật khẩu/khoá bí mật,
# build và chạy (Postgres, API Go, tracker Rust, landing Nuxt, CMS Vue, Caddy HTTPS),
# tự cập nhật khi nhánh git có commit mới, sao lưu database hằng ngày, cài lệnh quản lý `genz`.
#
# Dùng:
#   sudo bash install.sh --domain vayonline.genzbaothe.com [--cloudflare-token CF_TOKEN] [--email ban@gmail.com]
#                        [--branch main] [--token GITHUB_TOKEN] [--cloudflare-only] [--free-ports]
#
# --free-ports: nếu cổng 80/443 đang bị nginx/apache… chiếm thì tự tắt chúng (mặc định chỉ báo lỗi và dừng).
#
# Tên miền dùng DNS Cloudflare: thêm --cloudflare-token (API token quyền Zone › DNS › Edit) để Caddy xin chứng chỉ
# HTTPS qua DNS, chạy được cả khi bật proxy (đám mây cam). Script tự nhận ra Cloudflare và lấy đúng IP thật của khách.
#
# Chạy lại lệnh này bất kỳ lúc nào cũng an toàn: giữ nguyên dữ liệu và mật khẩu đã tạo.
set -euo pipefail

REPO="https://github.com/lhoaibao1/vayonline.genzbaothe.com.git"
BRANCH="main"
DIR="/opt/genzbaothe"
DOMAIN=""
EMAIL=""
TOKEN=""
INTERVAL="2min"
CF_TOKEN=""
CF_MODE="auto"      # auto: tự nhận biết; on: luôn bật cấu hình Cloudflare
CF_ONLY=0           # 1: tường lửa chỉ cho Cloudflare vào cổng 80/443
FREE_PORTS=0        # 1: tự tắt nginx/apache… đang chiếm cổng 80/443

say()  { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    --domain)   DOMAIN="${2:-}"; shift 2 ;;
    --email)    EMAIL="${2:-}"; shift 2 ;;
    --branch)   BRANCH="${2:-}"; shift 2 ;;
    --token)    TOKEN="${2:-}"; shift 2 ;;
    --repo)     REPO="${2:-}"; shift 2 ;;
    --dir)      DIR="${2:-}"; shift 2 ;;
    --interval) INTERVAL="${2:-}"; shift 2 ;;
    --cloudflare-token) CF_TOKEN="${2:-}"; CF_MODE="on"; shift 2 ;;
    --cloudflare) CF_MODE="on"; shift ;;
    --cloudflare-only) CF_ONLY=1; CF_MODE="on"; shift ;;
    --free-ports) FREE_PORTS=1; shift ;;
    -h|--help)  echo "Xem đầu file deploy/install.sh"; exit 0 ;;
    *) die "Không hiểu tham số: $1 (xem --help)" ;;
  esac
done

[ "$(id -u)" -eq 0 ] || die "Hãy chạy bằng quyền root (thêm sudo)."
[ -n "$DOMAIN" ] || die "Thiếu tên miền. Ví dụ: --domain genzbaothe.com"
command -v apt-get >/dev/null || die "Script hỗ trợ Ubuntu/Debian (cần apt-get)."
DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%%/*}"
[ -n "$EMAIL" ] || EMAIL="admin@${DOMAIN}"
export DEBIAN_FRONTEND=noninteractive

say "1/8 Cài gói hệ thống (git, curl, openssl, tường lửa)"
apt-get update -qq
apt-get install -y -qq ca-certificates curl git openssl ufw dnsutils iproute2 >/dev/null

say "2/8 Cài Docker"
if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh >/dev/null
fi
systemctl enable --now docker >/dev/null
docker compose version

say "3/8 Kiểm tra RAM và swap (build Rust/Nuxt cần khoảng 3GB)"
MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
SWAP_MB=$(awk '/SwapTotal/ {print int($2/1024)}' /proc/meminfo)
if [ $((MEM_MB + SWAP_MB)) -lt 3500 ] && [ ! -f /swapfile-genz ]; then
  echo "RAM ${MEM_MB}MB, swap ${SWAP_MB}MB: tạo thêm 3GB swap"
  fallocate -l 3G /swapfile-genz || dd if=/dev/zero of=/swapfile-genz bs=1M count=3072 status=none
  chmod 600 /swapfile-genz && mkswap /swapfile-genz >/dev/null && swapon /swapfile-genz
  grep -q swapfile-genz /etc/fstab || echo '/swapfile-genz none swap sw 0 0' >> /etc/fstab
else
  echo "RAM ${MEM_MB}MB, swap ${SWAP_MB}MB: đủ"
fi

say "4/8 Tải code (${BRANCH})"
mkdir -p /etc/genzbaothe && chmod 700 /etc/genzbaothe
if [ -n "$TOKEN" ]; then
  # Repo riêng tư: lưu token chỉ root đọc được, dùng cho cả lần tự cập nhật sau
  printf 'https://x-access-token:%s@github.com\n' "$TOKEN" > /etc/genzbaothe/git-credentials
  chmod 600 /etc/genzbaothe/git-credentials
fi
GIT=(git -c credential.helper="store --file=/etc/genzbaothe/git-credentials")
if [ -d "$DIR/.git" ]; then
  "${GIT[@]}" -C "$DIR" fetch -q origin "$BRANCH"
  "${GIT[@]}" -C "$DIR" checkout -q "$BRANCH" 2>/dev/null || "${GIT[@]}" -C "$DIR" checkout -q -b "$BRANCH" "origin/$BRANCH"
  "${GIT[@]}" -C "$DIR" reset -q --hard "origin/$BRANCH"
else
  "${GIT[@]}" clone -q --branch "$BRANCH" "$REPO" "$DIR" || die "Không tải được code. Repo riêng tư thì thêm --token <GitHub token có quyền đọc>; kiểm tra lại --branch."
fi
git -C "$DIR" config credential.helper "store --file=/etc/genzbaothe/git-credentials"
echo "Commit: $(git -C "$DIR" log -1 --format='%h %s')"

say "5/8 Tạo cấu hình (.env) với mật khẩu ngẫu nhiên"
ENV="$DIR/.env"
if [ ! -f "$ENV" ]; then
  cat > "$ENV" <<EOF
SITE_DOMAIN=${DOMAIN}
ACME_EMAIL=${EMAIL}
POSTGRES_PASSWORD=$(openssl rand -hex 24)
APP_KEY=$(openssl rand -hex 32)
COOKIE_SECURE=1
SEED_DEMO=0
EOF
  echo "Đã tạo $ENV"
else
  # Giữ mật khẩu cũ, chỉ cập nhật tên miền/email
  sed -i "s|^SITE_DOMAIN=.*|SITE_DOMAIN=${DOMAIN}|" "$ENV"
  grep -q '^ACME_EMAIL=' "$ENV" && sed -i "s|^ACME_EMAIL=.*|ACME_EMAIL=${EMAIL}|" "$ENV" || echo "ACME_EMAIL=${EMAIL}" >> "$ENV"
  echo "Giữ nguyên mật khẩu trong $ENV"
fi
if [ -n "$CF_TOKEN" ]; then
  grep -q '^CF_API_TOKEN=' "$ENV" && sed -i "s|^CF_API_TOKEN=.*|CF_API_TOKEN=${CF_TOKEN}|" "$ENV" || echo "CF_API_TOKEN=${CF_TOKEN}" >> "$ENV"
fi
chmod 600 "$ENV"
printf 'DIR=%s\nBRANCH=%s\n' "$DIR" "$BRANCH" > /etc/genzbaothe/genz.conf

# ---- Cloudflare ----
CF_V4="173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22"
CF_V6="2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32"
if V4=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v4) && V6=$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v6); then
  CF_V4=$(echo "$V4" | tr '\n' ' '); CF_V6=$(echo "$V6" | tr '\n' ' ')
fi
CF_PROXIED=0
if curl -sI --max-time 8 "http://${DOMAIN}" 2>/dev/null | grep -qi '^server: *cloudflare'; then CF_PROXIED=1; fi
if [ "$CF_MODE" = "auto" ] && [ "$CF_PROXIED" = 1 ]; then CF_MODE="on"; fi
mkdir -p "$DIR/deploy/local"
if [ "$CF_MODE" = "on" ]; then
  {
    echo "# Tạo bởi install.sh: website chạy sau Cloudflare"
    if grep -q '^CF_API_TOKEN=.' "$ENV"; then echo 'acme_dns cloudflare {env.CF_API_TOKEN}'; fi
    echo 'servers {'
    echo "	trusted_proxies static ${CF_V4} ${CF_V6}"
    echo '	client_ip_headers CF-Connecting-IP'
    echo '}'
  } > "$DIR/deploy/local/cloudflare.caddy"
  echo "Đã bật cấu hình Cloudflare (IP thật từ CF-Connecting-IP$(grep -q '^CF_API_TOKEN=.' "$ENV" && echo ', HTTPS xác minh qua DNS'))."
  if [ "$CF_PROXIED" = 1 ] && ! grep -q '^CF_API_TOKEN=.' "$ENV"; then
    warn "Tên miền đang bật proxy Cloudflare (đám mây cam) nhưng chưa có --cloudflare-token."
    warn "Caddy có thể không xin được chứng chỉ HTTPS. Chạy lại lệnh cài kèm --cloudflare-token, hoặc tạm chuyển bản ghi sang DNS only (đám mây xám)."
  fi
else
  rm -f "$DIR/deploy/local/cloudflare.caddy"
fi

say "6/8 Mở tường lửa (SSH, 80, 443)"
ufw allow OpenSSH >/dev/null || ufw allow 22/tcp >/dev/null
if [ "$CF_ONLY" = 1 ]; then
  # Chỉ Cloudflare được vào cổng web: chặn truy cập thẳng vào IP VPS
  ufw delete allow 80/tcp >/dev/null 2>&1 || true
  ufw delete allow 443/tcp >/dev/null 2>&1 || true
  for r in $CF_V4 $CF_V6; do
    ufw allow proto tcp from "$r" to any port 80,443 comment 'cloudflare' >/dev/null
  done
  echo "Cổng 80/443 chỉ mở cho dải IP Cloudflare."
else
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
fi
ufw --force enable >/dev/null
ufw status | head -8

# Cổng 80/443 phải trống cho Caddy (bỏ qua chính container của website khi chạy lại)
busy_ports() { ss -ltnpH '( sport = :80 or sport = :443 )' 2>/dev/null | grep -v docker-proxy || true; }
BUSY=$(busy_ports)
if [ -n "$BUSY" ]; then
  echo "Cổng 80/443 đang bị dùng:"; echo "$BUSY" | sed 's/^/  /'
  if [ "$FREE_PORTS" = 1 ]; then
    for svc in nginx apache2 httpd caddy lighttpd openresty; do
      if systemctl is-active --quiet "$svc" 2>/dev/null; then systemctl stop "$svc"; systemctl disable "$svc" >/dev/null 2>&1 || true; echo "Đã tắt $svc"; fi
    done
    sleep 2
    BUSY=$(busy_ports)
    [ -z "$BUSY" ] || die "Vẫn còn chương trình giữ cổng 80/443 (xem ở trên). Hãy tắt nó rồi chạy lại lệnh cài."
  else
    die "Cổng 80/443 đang bận. Nếu đó là nginx/apache không còn dùng, chạy lại lệnh cài và thêm --free-ports để tự tắt."
  fi
fi

say "7/8 Build và chạy (lần đầu mất 5–15 phút)"
cd "$DIR"
docker compose build
docker compose up -d --remove-orphans
docker compose restart caddy >/dev/null   # nạp lại cấu hình Cloudflare nếu vừa đổi

say "8/8 Cài tự cập nhật, sao lưu hằng ngày và lệnh genz"
install -m 755 "$DIR/deploy/update.sh" /usr/local/bin/genz-update
install -m 755 "$DIR/deploy/backup.sh" /usr/local/bin/genz-backup
install -m 755 "$DIR/deploy/genz" /usr/local/bin/genz
cat > /etc/systemd/system/genz-update.service <<EOF
[Unit]
Description=GenZbaothe.com: tự cập nhật khi git có commit mới
After=docker.service network-online.target
Wants=network-online.target
[Service]
Type=oneshot
ExecStart=/usr/local/bin/genz-update
EOF
cat > /etc/systemd/system/genz-update.timer <<EOF
[Unit]
Description=GenZbaothe.com: kiểm tra code mới mỗi ${INTERVAL}
[Timer]
OnBootSec=2min
OnUnitActiveSec=${INTERVAL}
[Install]
WantedBy=timers.target
EOF
cat > /etc/systemd/system/genz-backup.service <<EOF
[Unit]
Description=GenZbaothe.com: sao lưu database và ảnh tải lên
After=docker.service
[Service]
Type=oneshot
ExecStart=/usr/local/bin/genz-backup
EOF
cat > /etc/systemd/system/genz-backup.timer <<EOF
[Unit]
Description=GenZbaothe.com: sao lưu lúc 3 giờ sáng mỗi ngày
[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now genz-update.timer genz-backup.timer >/dev/null

# Chờ API sẵn sàng
for i in $(seq 1 60); do
  if docker compose exec -T api wget -qO- http://127.0.0.1:8080/api/healthz >/dev/null 2>&1; then break; fi
  sleep 2
done

IP=$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')
DNS_IP=$(dig +short A "$DOMAIN" | tail -1)
echo
docker compose ps --format 'table {{.Service}}\t{{.Status}}'
echo
printf '\033[1;32m✔ Đã cài xong GenZbaothe.com\033[0m\n'
echo "  Website:   https://${DOMAIN}"
echo "  Quản trị:  https://${DOMAIN}/admin/  (lần đầu mở để tạo tài khoản quản trị)"
if [ "$CF_PROXIED" = 1 ]; then
  echo "  Cloudflare: đang bật proxy. Vào SSL/TLS > Overview chọn chế độ 'Full (strict)'; bản ghi A ${DOMAIN} phải trỏ về ${IP}."
elif [ "$DNS_IP" != "$IP" ]; then
  warn "Tên miền ${DOMAIN} đang trỏ tới '${DNS_IP:-chưa có}', còn IP máy chủ là ${IP}."
  warn "Hãy tạo bản ghi A: ${DOMAIN} -> ${IP}. Caddy sẽ tự cấp HTTPS khi DNS đúng (không cần chạy lại)."
fi
echo
echo "  Tự cập nhật: mỗi ${INTERVAL} kiểm tra nhánh '${BRANCH}', có commit mới thì tự build và chạy lại."
echo "  Sao lưu:     3 giờ sáng hằng ngày vào /var/backups/genzbaothe (giữ 14 bản)."
echo "  Lệnh quản lý: genz status | genz logs [api|web|tracker|caddy|db] | genz update | genz backup | genz restart"
