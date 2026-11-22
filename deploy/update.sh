#!/usr/bin/env bash
# Tự cập nhật: nếu nhánh git có commit mới thì tải về, build rồi chạy lại.
# Build lỗi thì bản đang chạy vẫn giữ nguyên (chỉ thay container khi build thành công).
# Dùng: genz-update [--force]
set -euo pipefail
. /etc/genzbaothe/genz.conf
exec 9>/run/genz-update.lock
flock -n 9 || { echo "Đang có một lần cập nhật khác chạy, bỏ qua."; exit 0; }

cd "$DIR"
GIT=(git -c credential.helper="store --file=/etc/genzbaothe/git-credentials")
"${GIT[@]}" fetch -q origin "$BRANCH"
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH")
FAILED_FILE=/etc/genzbaothe/failed-commit
if [ "${1:-}" != "--force" ]; then
  [ "$LOCAL" = "$REMOTE" ] && exit 0
  # Commit này đã build lỗi: chờ commit mới, không build lại mỗi lần kiểm tra
  [ "$(cat "$FAILED_FILE" 2>/dev/null)" = "$REMOTE" ] && exit 0
fi

echo "Có code mới: $(git log -1 --format='%h %s' "$REMOTE")"
git reset -q --hard "$REMOTE"
# Script cập nhật/sao lưu cũng lấy bản mới
install -m 755 deploy/update.sh /usr/local/bin/genz-update
install -m 755 deploy/backup.sh /usr/local/bin/genz-backup
install -m 755 deploy/genz /usr/local/bin/genz

if ! docker compose build; then
  echo "Build lỗi ở commit $(git rev-parse --short HEAD). Website vẫn chạy bản cũ; sửa code rồi đẩy commit mới." >&2
  echo "$REMOTE" > "$FAILED_FILE"
  git reset -q --hard "$LOCAL"
  exit 1
fi
rm -f "$FAILED_FILE"
docker compose up -d --remove-orphans
docker image prune -f >/dev/null
echo "Đã chạy bản $(git log -1 --format='%h %s')"
