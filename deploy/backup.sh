#!/usr/bin/env bash
# Sao lưu database PostgreSQL và ảnh tải lên vào /var/backups/genzbaothe, giữ 14 bản gần nhất.
set -euo pipefail
. /etc/genzbaothe/genz.conf
OUT=/var/backups/genzbaothe
KEEP=14
mkdir -p "$OUT" && chmod 700 "$OUT"
cd "$DIR"
STAMP=$(date +%Y%m%d-%H%M)

docker compose exec -T db pg_dump -U genz -d genz --no-owner | gzip > "$OUT/db-$STAMP.sql.gz"
docker compose exec -T api tar -C /data -czf - uploads > "$OUT/uploads-$STAMP.tar.gz"
chmod 600 "$OUT"/*-"$STAMP".*

# Xoá bản cũ
ls -1t "$OUT"/db-*.sql.gz 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -f
ls -1t "$OUT"/uploads-*.tar.gz 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -f
echo "Đã sao lưu: $OUT/db-$STAMP.sql.gz, $OUT/uploads-$STAMP.tar.gz"
