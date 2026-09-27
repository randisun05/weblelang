# Pekerjaan Tertunda

Daftar hal yang sengaja ditunda. Semua fitur sudah selesai di kode (PR #2); yang tersisa adalah langkah
yang perlu dilakukan pemilik proyek. Centang saat selesai.

## Repositori
- [ ] Tinjau & merge PR #2 (`claude/web-lelang-barang-titipan-9nm1q0` → `main`).
- [ ] Jadikan `main` sebagai *default branch* (GitHub → Settings → General → Default branch).

## Legal
- [ ] Minta ahli hukum meninjau Syarat & Ketentuan dan Kebijakan Privasi.
- [ ] Isi identitas penyelenggara `LEGAL_*` di `.env`.
- [ ] Daftar PSE Lingkup Privat (PP 71/2019).

## Akun layanan (isi di `.env`)
- [ ] Payment gateway Midtrans atau Xendit → uji sandbox (`docs/UJI-SANDBOX.md`) lalu `php artisan payments:check`.
- [ ] WhatsApp: Fonnte atau Wablas (`WHATSAPP_DRIVER` + token) → `php artisan whatsapp:test 08xxxxxxxxxx`.
- [ ] Cloudflare Turnstile (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`).
- [ ] (Opsional) Real-time Reverb: `REVERB_*`, `BROADCAST_CONNECTION=reverb`.
- [ ] (Opsional) Sentry: `SENTRY_LARAVEL_DSN`.

## Server & deploy
- [ ] Deploy pertama (Docker atau VPS) sesuai `docs/DEPLOY.md`.
- [ ] Isi secrets/variables `DEPLOY_*` di GitHub Actions agar deploy otomatis aktif.
- [ ] Backup: isi `BACKUP_ARCHIVE_PASSWORD`, simpan salinan di luar server, uji restore.
- [ ] Setelah online: pastikan `APP_URL` benar, daftarkan `sitemap.xml` di Google Search Console.
- [ ] Jalankan checklist go-live di `docs/DEPLOY.md`.
