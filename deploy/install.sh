#!/usr/bin/env bash
# GenZbaothe.com: cài đặt toàn bộ lên VPS bằng một lệnh (Ubuntu 22.04/24.04, Debian 12).
#
# Tự làm: cài Docker + Git + tường lửa, tạo swap nếu thiếu RAM, tải code, sinh mật khẩu/khoá bí mật,
# build và chạy (Postgres, API Go, tracker Rust, landing Nuxt, CMS Vue, Caddy HTTPS),
# tự cập nhật khi nhánh git có commit mới, sao lưu database hằng ngày, cài lệnh quản lý `genz`.
#
# Dùng:
#   sudo bash install.sh --domain genzbaothe.com [--email ban@gmail.com] [--branch main] [--token GITHUB_TOKEN]
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
    -h|--help)  sed -n '2,13p' "$0"; exit 0 ;;
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
apt-get install -y -qq ca-certificates curl git openssl ufw dnsutils >/dev/null

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
chmod 600 "$ENV"
printf 'DIR=%s\nBRANCH=%s\n' "$DIR" "$BRANCH" > /etc/genzbaothe/genz.conf

say "6/8 Mở tường lửa (SSH, 80, 443)"
ufw allow OpenSSH >/dev/null || ufw allow 22/tcp >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ufw status | head -8

say "7/8 Build và chạy (lần đầu mất 5–15 phút)"
cd "$DIR"
docker compose build
docker compose up -d --remove-orphans

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
if [ "$DNS_IP" != "$IP" ]; then
  warn "Tên miền ${DOMAIN} đang trỏ tới '${DNS_IP:-chưa có}', còn IP máy chủ là ${IP}."
  warn "Hãy tạo bản ghi A: ${DOMAIN} -> ${IP}. Caddy sẽ tự cấp HTTPS khi DNS đúng (không cần chạy lại)."
fi
echo
echo "  Tự cập nhật: mỗi ${INTERVAL} kiểm tra nhánh '${BRANCH}', có commit mới thì tự build và chạy lại."
echo "  Sao lưu:     3 giờ sáng hằng ngày vào /var/backups/genzbaothe (giữ 14 bản)."
echo "  Lệnh quản lý: genz status | genz logs [api|web|tracker|caddy|db] | genz update | genz backup | genz restart"
