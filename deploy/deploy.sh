#!/usr/bin/env bash
# Deploy tanpa downtime berbasis rilis ke VPS (tanpa Docker).
#
# Struktur di server:
#   /var/www/weblelang/releases/<timestamp>   rilis-rilis
#   /var/www/weblelang/shared/.env            konfigurasi produksi
#   /var/www/weblelang/shared/storage         upload, log, backup (dipakai semua rilis)
#   /var/www/weblelang/current -> releases/…  symlink rilis aktif
#
# Pemakaian (di server, sebagai user deploy):  bash deploy.sh [branch]
set -euo pipefail

APP_DIR=/var/www/weblelang
REPO=${REPO:-https://github.com/randisun05/weblelang.git}
BRANCH=${1:-main}
RELEASE=$APP_DIR/releases/$(date +%Y%m%d%H%M%S)
KEEP=5

echo "→ Mengambil kode ($BRANCH)"
git clone --depth 1 --branch "$BRANCH" "$REPO" "$RELEASE"
cd "$RELEASE"

echo "→ Menautkan .env & storage bersama"
ln -s "$APP_DIR/shared/.env" .env
rm -rf storage
ln -s "$APP_DIR/shared/storage" storage
mkdir -p "$APP_DIR"/shared/storage/{app/public,app/private,framework/{cache,sessions,views},logs}

echo "→ Dependensi & build aset"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

echo "→ Migrasi & cache"
php artisan migrate --force
php artisan storage:link
php artisan optimize

echo "→ Mengaktifkan rilis"
ln -sfn "$RELEASE" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

echo "→ Memuat ulang layanan"
sudo systemctl reload php8.3-fpm
php artisan queue:restart
php artisan reverb:restart >/dev/null 2>&1 || true

echo "→ Membersihkan rilis lama (menyisakan $KEEP)"
ls -1dt "$APP_DIR"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "→ Cek kesehatan"
php artisan payments:check || echo "⚠ Periksa konfigurasi payment gateway di atas."
curl -fsS "$(grep ^APP_URL "$APP_DIR/shared/.env" | cut -d= -f2)/up" >/dev/null && echo "✓ Aplikasi merespons"
echo "✓ Deploy selesai: $RELEASE"
