# WebLelang — Lelang Online Barang Titipan

Platform lelang online untuk **barang titipan (konsinyasi)**: terima barang dari penitip → periksa & foto →
lelang online dengan peserta terverifikasi → tagih pemenang → serahkan barang → setor hasil ke penitip.

Stack mengikuti project **web-aspro**: **Laravel 12 · PHP 8.3 · Vue 3 · Inertia.js · Vite**, Laravel Fortify (2FA),
Midtrans Snap, SweetAlert2, plus Tailwind CSS v4.

📄 **Rencana lengkap (riset, alur bisnis, keamanan, roadmap): [`docs/RENCANA.md`](docs/RENCANA.md)**

## Fitur yang sudah diimplementasikan (Fase 1 / MVP)

**Publik & peserta**
- Beranda dengan lot live, sesi, dan "segera dilelang"; jadwal lelang; halaman sesi dengan filter & urutan.
- Halaman lot: galeri, spesifikasi dinamis, countdown berbasis **waktu server**, harga & riwayat bid yang
  diperbarui otomatis (polling; siap Laravel Reverb), tombol bid cepat, **auto-bid (proxy)**, konfirmasi total + premi.
- Registrasi, login, reset password; profil & **KYC** (NIK terenkripsi, foto KTP di disk privat).
- Daftar sesi berjaminan (unggah bukti jaminan), watchlist, dashboard peserta, invoice + bayar via
  **Midtrans Snap** atau unggah bukti transfer.

**Mesin lelang** (`app/Services/Auction`)
- Bid atomik dengan row lock, kelipatan bertingkat (config), proteksi salah ketik, **anti-sniping**,
  penutupan bertahap per lot, **hash chain** anti-manipulasi riwayat bid.
- Penutupan otomatis (`auctions:tick` tiap menit + penutupan "malas" saat halaman dibuka): cek harga limit,
  buat invoice (premi pembeli + biaya admin), status barang terjual/tidak laku.
- Invoice lunas → **settlement penitip** otomatis (komisi per barang → per penitip → default).

**Panel admin** (`/admin`)
- Dashboard angka penting & daftar "perlu tindakan".
- Penitip (rekening terenkripsi), barang titipan dengan alur status
  `Diterima → Diperiksa → Disetujui → Dilelang → Terjual → Diserahkan`, foto multi-upload (re-encode WebP, EXIF dihapus).
- Kategori dengan **atribut dinamis** (tanpa migrasi) → reusable untuk kendaraan, properti, perhiasan, dsb.
- Sesi lelang: jadwal, jaminan, premi, anti-sniping, jeda tutup; tambah lot dari barang siap lelang; terbitkan/tarik; batalkan lot.
- Peserta & verifikasi KYC, blokir; persetujuan jaminan; invoice (konfirmasi lunas, batal/wanprestasi, serah terima);
  settlement (tandai transfer + bukti); **log audit**; manajemen petugas (super admin).
- Peran: `super_admin`, `admin` (2FA wajib), `staff` (gudang), `bidder`.

## Menjalankan secara lokal

```bash
composer install
npm install && npm run build        # atau: npm run dev
cp .env.example .env && php artisan key:generate
# untuk demo lokal tanpa authenticator: set AUTH_ENFORCE_2FA=false di .env
touch database/database.sqlite      # atau atur DB MySQL di .env
php artisan migrate --seed
php artisan storage:link
php artisan serve                   # http://127.0.0.1:8000
php artisan schedule:work           # (terminal lain) buka/tutup lot tiap menit
```

Akun demo (password `password`, **ganti di production**):

| Peran | Email |
|---|---|
| Super admin | superadmin@weblelang.test |
| Admin | admin@weblelang.test |
| Staf gudang | staf@weblelang.test |
| Peserta terverifikasi | budi@contoh.test, sari@contoh.test |
| Peserta belum KYC | andi@contoh.test |

## Konfigurasi aturan lelang

Semua kebijakan ada di [`config/auction.php`](config/auction.php) (+ variabel `.env`): tabel kelipatan, komisi & premi default,
biaya admin, batas bayar invoice, anti-sniping, wajib KYC, rekening transfer. Midtrans di `config/midtrans.php`.
URL notifikasi Midtrans: `POST /payments/midtrans/notification`.

## Pengujian

```bash
php artisan test          # 41 tes: mesin bid, proxy, anti-sniping, penutupan, invoice/settlement,
                          # otorisasi, webhook Midtrans, alur admin end-to-end
vendor/bin/pint --test    # gaya kode
```

## Deploy (ringkas)

PHP 8.3, MySQL 8/MariaDB, HTTPS wajib, `APP_DEBUG=false`, `AUTH_ENFORCE_2FA=true`, cron
`* * * * * php artisan schedule:run`, `php artisan queue:work`, `php artisan storage:link`, backup DB harian.
