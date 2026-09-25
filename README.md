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

**Fase 2 (sebagian, sudah diimplementasikan)**
- **Notifikasi** email + lonceng in-app: bid terlampaui, lot pantauan/ditawar segera berakhir, menang lelang,
  invoice lunas/dibatalkan, hasil KYC, jaminan dikembalikan/hangus. Dikirim lewat antrean (queue).
- **Invoice kedaluwarsa dibatalkan otomatis** oleh scheduler (wanprestasi) → barang siap dilelang ulang,
  jaminan pemenang pada sesi itu otomatis hangus.
- **Uang jaminan**: status ditahan → dikembalikan / hangus, dikelola dari halaman sesi.
- **PDF**: invoice (peserta & admin) dan berita acara serah terima barang (BAST).
- **Laporan & ekspor Excel**: ringkasan periode, Excel penjualan dan Excel settlement (siap untuk daftar transfer).
- **Portal penitip** tanpa login: link bertanda tangan, kedaluwarsa, dan bisa dicabut; bisa dikirim via WhatsApp.

**Metode lelang (dipilih per sesi)**
- 🔨 **Terbuka** — harga naik terbuka, tutup otomatis sesuai jadwal, auto-bid, anti-sniping; opsi **⚡ Beli Langsung**
  per lot (tersedia sampai penawaran pertama, tidak boleh di bawah harga limit).
- ✉️ **Penawaran tertutup** — nominal, jumlah, dan pemimpin dirahasiakan dari semua pihak **termasuk admin** sampai lot ditutup;
  peserta boleh mengubah penawaran sebelum tenggat, penawaran final tertinggi menang (seri → yang lebih dulu).
- 🎙️ **Live juru lelang** — konsol juru lelang (buka lot → panggilan pertama → kedua → ketuk palu), siaran YouTube/Vimeo
  tertanam, bid baru otomatis membatalkan panggilan sehingga palu tidak bisa "mendahului" penawaran terakhir.

**Payment gateway (multi-driver)** — `app/Payments`
- Driver **Midtrans** (Snap + Iris), **Xendit** (Invoice + Disbursement), dan **Simulator** (lokal/demo, ditolak di production);
  pilih lewat `PAYMENT_GATEWAY` / `PAYOUT_GATEWAY`. Gateway baru cukup menambah satu kelas driver.
- Uang masuk: **bayar invoice** & **setor jaminan online** (pendaftaran sesi otomatis disetujui saat lunas).
- Uang keluar: **payout ke penitip** (klik admin) & **refund jaminan otomatis** setelah sesi selesai
  (peserta kalah, atau pemenang yang sudah lunas; butuh rekening peserta di profil).
- Webhook diverifikasi per driver (signature SHA-512 / callback token), nominal dicek, diproses idempoten dengan row lock;
  satu tagihan hanya boleh punya satu payout aktif (anti transfer ganda). Log di menu **Transaksi Gateway**.

| URL webhook (daftarkan di dashboard gateway) | Untuk |
|---|---|
| `POST /payments/webhook/midtrans` (atau alias lama `/payments/midtrans/notification`) | Notifikasi pembayaran Snap |
| `POST /payouts/webhook/midtrans` | Notifikasi Iris (header `Iris-Signature`) |
| `POST /payments/webhook/xendit` | Callback invoice (header `x-callback-token`) |
| `POST /payouts/webhook/xendit` | Callback disbursement |

### Go-live & pertumbuhan
- **Legal**: halaman Syarat & Ketentuan dan Kebijakan Privasi (UU PDP), persetujuan tercatat per versi,
  unduh data pribadi dari profil. Teks wajib ditinjau ahli hukum sebelum go-live.
- **Titip barang online** (`/titip-barang`): calon penitip mengunggah foto & data barang; admin meninjau di
  menu **Pengajuan Titip** lalu menerima (penitip + barang dibuat otomatis) atau menolak dengan alasan.
- **Real-time** dengan Laravel Reverb (opsional): harga, panggilan juru lelang, dan notifikasi langsung
  masuk ke browser; polling tetap menjadi cadangan.
- **Keamanan**: Cloudflare Turnstile di login/registrasi/lupa sandi/form titip, verifikasi email wajib
  sebelum menawar, deteksi indikasi *shill bidding* (perangkat/IP sama, penitip ikut menawar) di menu **Kecurigaan**.
- **SEO & berbagi**: meta tag + Open Graph dirender server (preview WhatsApp/Facebook), JSON-LD Product/Event,
  `sitemap.xml`, `robots.txt` (staging otomatis tidak terindeks), watermark teks pada foto barang.
- **Deploy**: Docker (FrankenPHP + HTTPS otomatis) atau VPS (Nginx + Supervisor), backup terenkripsi,
  `/health`, Sentry, `php artisan payments:check`. Lihat `docs/DEPLOY.md` dan `docs/UJI-SANDBOX.md`.

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
php artisan schedule:work           # (terminal lain) buka/tutup lot, pengingat, invoice kedaluwarsa
php artisan queue:work              # (terminal lain) kirim notifikasi email & in-app
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
biaya admin, batas bayar invoice, anti-sniping, wajib KYC, rekening transfer. Gateway pembayaran di
[`config/payments.php`](config/payments.php) (driver, kredensial, daftar kode bank).

## Pengujian

```bash
php artisan test          # 84 tes (termasuk metode tertutup, beli langsung, live): mesin bid, proxy, anti-sniping, penutupan, invoice/settlement,
                          # otorisasi, alur admin end-to-end, notifikasi, invoice kedaluwarsa, jaminan,
                          # PDF, Excel, portal penitip, payment gateway (Midtrans/Xendit/simulator),
                          # payout & refund otomatis
vendor/bin/pint --test    # gaya kode
```

## Deploy (ringkas)

PHP 8.3, MySQL 8/MariaDB, HTTPS wajib, `APP_DEBUG=false`, `AUTH_ENFORCE_2FA=true`, cron
`* * * * * php artisan schedule:run`, `php artisan queue:work`, `php artisan storage:link`, backup DB harian.
