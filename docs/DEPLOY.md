# Panduan Deploy & Go-Live WebLelang

Dua cara deploy: **Docker** (paling mudah, HTTPS otomatis) atau **VPS biasa** (nginx + PHP-FPM + Supervisor).
Keduanya membutuhkan: domain yang mengarah ke server, PHP 8.3 (sudah ada di image Docker), MySQL 8, dan akses SMTP untuk email.

---

## A. Deploy dengan Docker (disarankan)

Kebutuhan server: Linux dengan Docker + Docker Compose, port 80/443 terbuka, DNS domain mengarah ke IP server.

```bash
git clone https://github.com/randisun05/weblelang.git && cd weblelang
cp .env.example .env
nano .env                       # isi sesuai tabel "Konfigurasi produksi" di bawah
docker compose -f docker-compose.prod.yml run --rm web artisan key:generate --show   # salin ke APP_KEY di .env
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml run --rm web artisan db:seed --class=CategorySeeder --force
docker compose -f docker-compose.prod.yml run --rm web artisan db:seed --force   # membuat akun petugas awal
```

Isi `SERVER_NAME=lelang.domainanda.id` di `.env` → Caddy mengambil sertifikat HTTPS Let's Encrypt otomatis.
Layanan yang berjalan: `web` (HTTPS + PHP), `queue` (notifikasi & job), `scheduler` (lot, pengingat, refund, backup), `db` (MySQL).

Update versi baru:
```bash
git pull && docker compose -f docker-compose.prod.yml up -d --build
```
Migrasi database dijalankan otomatis oleh container `web` saat start (`RUN_MIGRATIONS=false` untuk menonaktifkan).

> Build di jaringan kantor dengan proxy TLS? Letakkan sertifikat CA proxy di `docker/certs/*.crt`.

## B. Deploy di VPS tanpa Docker

Paket: `nginx`, `php8.3-fpm` + ekstensi `mysql gd intl zip mbstring xml curl`, `mysql-server`, `supervisor`,
`composer`, Node.js 22, `certbot`, dan `mysqldump` (bagian dari mysql-client, untuk backup).

1. Siapkan folder: `/var/www/weblelang/{releases,shared/storage}` dan `/var/www/weblelang/shared/.env`.
2. Jalankan `deploy/deploy.sh` (clone → build → migrasi → aktifkan rilis tanpa downtime).
3. Nginx: salin `deploy/nginx.conf`, ganti domain, `nginx -t && systemctl reload nginx`, lalu `certbot --nginx -d DOMAIN`.
4. Queue worker (+ websocket): salin `deploy/supervisor.conf` ke `/etc/supervisor/conf.d/`, `supervisorctl update`.
5. Scheduler: pasang `deploy/crontab` untuk user `www-data`.

---

## Konfigurasi produksi (`.env`)

| Variabel | Nilai produksi |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://lelang.domainanda.id` |
| `APP_FORCE_HTTPS` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `AUTH_ENFORCE_2FA` | `true` (wajib 2FA admin) |
| `TRUSTED_PROXIES` | `*` bila di belakang Cloudflare/load balancer, kosongkan bila tidak |
| `DB_*` | kredensial MySQL; untuk Docker isi juga `DB_ROOT_PASSWORD` |
| `MAIL_*` | SMTP sungguhan (notifikasi, reset password, verifikasi email) |
| `PAYMENT_GATEWAY` / `PAYOUT_GATEWAY` | `midtrans` atau `xendit` (**jangan** `simulator`) — lihat `docs/UJI-SANDBOX.md` |
| `BACKUP_ARCHIVE_PASSWORD` | kata sandi panjang acak — arsip backup berisi KTP & data pribadi, **wajib terenkripsi** |
| `BACKUP_DISKS` | `backups` (lokal) — disarankan tambah penyimpanan di luar server, mis. `backups,s3` + kredensial `AWS_*` |
| `BACKUP_NOTIFY_EMAIL` | email penerima laporan backup gagal/tidak sehat |
| `SENTRY_LARAVEL_DSN` | (opsional) pelacakan error ke Sentry |
| `LEGAL_*` | identitas penyelenggara untuk Syarat & Ketentuan / Kebijakan Privasi |
| `SERVER_NAME` | (Docker) domain untuk HTTPS otomatis |

## Real-time (Laravel Reverb, opsional)

Tanpa Reverb, halaman lot memperbarui harga dengan polling tiap beberapa detik. Dengan Reverb,
perubahan (bid, panggilan juru lelang, lot dibuka/ditutup) langsung terkirim ke browser, dan
notifikasi seperti "Anda terlampaui" muncul sebagai toast.

1. Isi `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
   (string acak, mis. `openssl rand -hex 16`), dan `REVERB_ALLOWED_ORIGINS=lelang.domainanda.id`.
2. **Docker**: tambahkan `COMPOSE_PROFILES=realtime` lalu `docker compose -f docker-compose.prod.yml up -d --build`
   (build ulang wajib, karena kunci websocket dibaca saat build aset). Caddy meneruskan `/app/*` ke service `reverb`.
3. **VPS**: aktifkan program `weblelang-reverb` di `deploy/supervisor.conf`; `deploy/nginx.conf` sudah meneruskan
   `/app/` ke `127.0.0.1:8080`. Isi `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, lalu `npm run build`.

Event websocket hanya berisi `lot_id` dan jenis perubahan. Browser tetap mengambil data dari server,
jadi aturan penyembunyian (reserve price, lelang tertutup) tidak bisa bocor lewat websocket; bid pada
lelang tertutup bahkan tidak disiarkan sama sekali. Bila websocket terputus, polling otomatis kembali normal.

## Monitoring

- **`GET /health`** → `200` bila database, cache, scheduler, queue worker, dan ruang disk sehat; `503` bila ada yang
  bermasalah (mis. worker mati). Pasang di UptimeRobot / Better Stack dengan interval 1–5 menit.
- **`GET /up`** → cek ringan bawaan Laravel (dipakai healthcheck Docker).
- **Sentry** → isi `SENTRY_LARAVEL_DSN` untuk menerima notifikasi setiap error aplikasi.
- **Log Audit** di panel admin mencatat aksi petugas, pembayaran, dan payout.

## Backup

Terjadwal otomatis setiap hari: `backup:clean` (01:00), `backup:run` (01:30), `backup:monitor` (07:00).
Isi backup: dump database + `storage/app/private` (KTP, bukti transfer) + `storage/app/public` (foto barang),
dikompres ZIP **terenkripsi AES-256** dengan `BACKUP_ARCHIVE_PASSWORD`. Retensi: harian 16 hari, mingguan 8 minggu,
bulanan 4 bulan, tahunan 2 tahun (ubah di `config/backup.php`).

Uji manual: `php artisan backup:run` lalu `php artisan backup:list`.
**Uji restore minimal sekali** sebelum go-live: ekstrak arsip dengan kata sandinya, impor `db-dumps/*.sql` ke database kosong.

## Checklist go-live

- [ ] Model usaha & perizinan lelang dikonsultasikan dengan ahli hukum; `LEGAL_*` diisi; Syarat & Ketentuan
      dan Kebijakan Privasi ditinjau ahli hukum.
- [ ] Terdaftar sebagai PSE Lingkup Privat (PP 71/2019).
- [ ] HTTPS aktif, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`.
- [ ] Ganti semua kata sandi akun seed (`superadmin@…`, `admin@…`, `staf@…`) dan aktifkan 2FA.
- [ ] `php artisan payments:check` lolos dengan kunci production; uji sandbox (`docs/UJI-SANDBOX.md`) selesai.
- [ ] Queue worker & scheduler berjalan — `/health` mengembalikan `200`.
- [ ] Backup berjalan, tersimpan di luar server, dan restore sudah diuji.
- [ ] Uptime monitor & Sentry aktif; email SMTP terkirim (uji lupa kata sandi).
