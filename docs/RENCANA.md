# Rencana Lengkap — Web Lelang Barang Titipan ("WebLelang")

> Stack mengikuti project **web-aspro** (`randisun05/asprov1`): **Laravel 12 · PHP 8.3 · Vue 3 · Inertia.js · Vite**,
> auth **Laravel Fortify** (2FA wajib untuk admin), pembayaran **Midtrans Snap** + opsi transfer manual,
> pola folder `Controllers/{Admin,Public,User}`, `Pages/{Admin,Public,User}`, `Services/`, flash message global.

---

## 1. Ringkasan

WebLelang adalah platform lelang online untuk **barang titipan (konsinyasi)**: pemilik barang (**penitip**)
menitipkan barang ke kita, kita memeriksa, memotret, menentukan harga limit bersama penitip, lalu melelangnya
secara online kepada **peserta lelang** yang sudah terverifikasi. Setelah pemenang membayar, barang diserahkan,
dan hasil penjualan dikurangi komisi disetorkan ke penitip (**settlement**).

Tiga sasaran utama:

| Sasaran | Wujud di sistem |
|---|---|
| **Aman** | Bid diproses atomik (row lock DB), waktu server otoritatif, audit trail berantai hash, KYC peserta, 2FA admin, webhook pembayaran ditandatangani, data sensitif dienkripsi. |
| **Menarik** | Halaman lot dengan galeri foto, countdown real-time, riwayat bid live, badge "Hampir berakhir", watchlist, UI responsif mobile-first. |
| **Manajemen mudah** | Alur kerja satu arah (Terima → Periksa → Jadwalkan → Lelang → Tagih → Serahkan → Setor), dashboard angka penting, semua aturan (kelipatan, komisi, premi, anti-sniping) diatur dari konfigurasi/pengaturan, bukan kode. |
| **Bisa dipakai di hal lain** | Mesin lelang (`App\Services\Auction`) terpisah dari domain "barang titipan"; kategori punya atribut dinamis (JSON schema) sehingga bisa dipakai untuk kendaraan, properti, elektronik, barang sitaan, lelang amal, bahkan *reverse auction* pengadaan. |

---

## 2. Riset: Cara Kerja Lelang Barang Titipan

### 2.1 Model bisnis konsinyasi

1. **Penitip** menyerahkan barang + dokumen kepemilikan (nota, BPKB, sertifikat, dsb).
2. Balai/penyelenggara membuat **Perjanjian Titip Lelang**: harga limit (*reserve price*), komisi penjual,
   jangka waktu titip, biaya penyimpanan/foto (opsional), ketentuan jika tidak laku (dikembalikan / dilelang ulang
   dengan limit turun).
3. Barang diperiksa (kondisi, kelengkapan, keaslian), difoto, diberi **kode barang** dan lokasi gudang.
4. Barang dimasukkan ke **sesi lelang** sebagai **lot**.
5. Peserta mendaftar, verifikasi identitas (KTP), dan — untuk sesi tertentu — menyetor **uang jaminan**.
6. Lelang berlangsung; penawar tertinggi saat penutupan menang **jika harga ≥ harga limit**.
7. Pemenang menerima **invoice**: harga palu (*hammer price*) + **premi pembeli** (*buyer's premium*) + biaya admin.
   Batas bayar biasanya 1–3 hari kerja; bila gagal bayar → jaminan hangus, lot ditawarkan ke runner-up/dilelang ulang.
8. Setelah lunas, barang diambil/dikirim (berita acara serah terima).
9. **Settlement** ke penitip: harga palu − komisi − biaya lain = dana bersih, ditransfer ke rekening penitip.

### 2.2 Praktik terbaik dari platform lelang (lelang.go.id DJKN, balai lelang swasta, eBay, Catawiki, LiveAuctioneers)

| Praktik | Keterangan | Status di rencana |
|---|---|---|
| **Harga limit (reserve)** | Lot tidak terjual jika penawaran tertinggi < limit. Nilai limit tidak ditampilkan, cukup indikator "Limit belum tercapai". | MVP |
| **Kelipatan penawaran (bid increment)** | Tabel bertingkat berdasarkan harga saat ini (mis. < 1 jt: +50rb; 1–10 jt: +100rb; …). | MVP (config) |
| **Anti-sniping / soft close** | Bid di N menit terakhir memperpanjang waktu tutup M menit. | MVP |
| **Proxy / auto-bid** | Peserta memasang penawaran maksimum; sistem menawar otomatis sekecil mungkin. | MVP |
| **Penutupan bertahap (staggered close)** | Lot dalam satu sesi tutup bergiliran (mis. selang 1 menit) supaya peserta fokus. | MVP (opsi) |
| **Uang jaminan** | Syarat ikut sesi; dikembalikan jika kalah, hangus jika wanprestasi. | MVP (manual verifikasi), Fase 2 (Midtrans + refund otomatis) |
| **Penawaran tertutup (sealed bid)** | Seperti lelang.go.id "closed bidding" — penawaran tidak terlihat hingga penutupan. | Fase 2 (mode lot) |
| **Beli sekarang (buy now)** | Harga langsung beli sebelum ada bid. | Fase 3 |
| **Watchlist & notifikasi** | Email/WA saat dilampaui (*outbid*), 15 menit sebelum tutup, menang. | MVP (watchlist), Fase 2 (notifikasi) |
| **Riwayat bid transparan** | Nama penawar disamarkan (mis. `Pes***12`). | MVP |
| **Laporan untuk penitip** | Penitip bisa melihat status barang dan settlement. | Fase 2 (portal penitip) |

### 2.3 Aspek hukum & kepatuhan (Indonesia) — **wajib dikonsultasikan dengan ahli hukum**

- Lelang resmi di Indonesia diatur Kementerian Keuangan (DJKN). Juklak lelang: **PMK 213/PMK.06/2020**.
  Lelang atas barang milik perorangan/swasta yang dijual sukarela termasuk **Lelang Noneksekusi Sukarela**, yang
  lazimnya diselenggarakan melalui **Balai Lelang** berizin dan dipimpin **Pejabat Lelang Kelas II**.
  Opsi model usaha:
  1. Bekerja sama/menjadi **Balai Lelang berizin** (paling aman; ada bea lelang & risalah lelang), atau
  2. Memosisikan platform sebagai **penjualan konsinyasi dengan sistem penawaran** (bukan "lelang" dalam arti hukum)
     dengan syarat & ketentuan yang jelas.
  Periksa versi terbaru peraturan Balai Lelang & bea lelang sebelum go-live.
- **UU 27/2022 tentang Pelindungan Data Pribadi**: KTP/NIK, rekening, alamat = data pribadi; perlu dasar pemrosesan
  (persetujuan), enkripsi, retensi, hak hapus/akses, dan pemberitahuan kebocoran.
- **PP 71/2019 (PSTE)**: daftar sebagai **PSE Lingkup Privat** di Komdigi.
- Perpajakan: PPh/PPN atas komisi & premi pembeli — konsultasikan dengan konsultan pajak.
- Syarat & ketentuan wajib memuat: status barang "apa adanya" (*as is*), aturan wanprestasi, jaminan, dan penyelesaian sengketa.

---

## 3. Aktor & Hak Akses

| Peran | Akses |
|---|---|
| `super_admin` | Semua, termasuk pengaturan, manajemen pengguna, log audit. 2FA wajib. |
| `admin` | Operasional penuh: penitip, barang, sesi, lot, KYC, invoice, settlement. 2FA wajib. |
| `staff` | Gudang/operasional: input & inspeksi barang, foto, serah terima. Tidak bisa menyetujui pembayaran/settlement. |
| `bidder` | Peserta: profil & KYC, daftar sesi, bid, watchlist, invoice & pembayaran. |
| *Penitip* | Fase 1: data dikelola admin (tidak login). Fase 2: portal penitip (read-only status & settlement). |

Otorisasi di-*enforce* di server via middleware `role:` + Policy, **bukan hanya disembunyikan di UI**
(pelajaran dari web-aspro: aturan yang dulu hanya di UI dipindahkan ke server).

---

## 4. Alur Bisnis End-to-End

```
 PENITIP                ADMIN/STAFF                          PESERTA                    SISTEM
    │  serahkan barang       │                                   │                          │
    ├──────────────────────► │ 1. Terima (status: received)      │                          │
    │                        │ 2. Inspeksi + foto (inspected)    │                          │
    │  setujui limit/komisi  │ 3. Setujui (approved)             │                          │
    ├──────────────────────► │                                   │                          │
    │                        │ 4. Buat sesi + tambahkan lot      │                          │
    │                        │    (item: listed, lot: scheduled) │                          │
    │                        │                                   │ daftar + KYC             │
    │                        │ 5. Verifikasi KYC ◄───────────────┤                          │
    │                        │                                   │ bid / auto-bid ────────► │ lock, validasi,
    │                        │                                   │                          │ anti-snipe, hash
    │                        │                                   │                          │ 6. tutup lot (cron/menit)
    │                        │                                   │ ◄── invoice (jika ≥limit)│    sold / unsold
    │                        │                                   │ bayar (Midtrans/transfer)│
    │                        │ 7. Konfirmasi bayar (paid) ◄──────┤                          │
    │                        │ 8. Serah terima barang ──────────►│                          │
    │ ◄──────────────────────┤ 9. Settlement (net = palu − komisi)                          │
    │                        │    unsold → relist / kembalikan ke penitip                   │
```

State machine utama:

- **Item**: `received → inspected → approved → listed → sold → delivered` | `listed → unsold → (approved | returned)`
- **Lot**: `scheduled → live → (sold | unsold | cancelled)`
- **Invoice**: `unpaid → paid` | `unpaid → expired/cancelled`
- **Settlement**: `pending → paid`

---

## 5. Modul & Fitur

### 5.1 Publik
- Beranda: hero, lot **sedang berlangsung**, **segera dimulai**, kategori, "cara ikut lelang".
- Daftar sesi lelang & halaman sesi (daftar lot, filter kategori, urutkan "segera berakhir").
- Halaman lot: galeri, spesifikasi (atribut dinamis kategori), kondisi, estimasi, harga saat ini, jumlah bid,
  countdown berbasis **waktu server**, form bid dengan kelipatan, auto-bid, riwayat bid (nama disamarkan),
  indikator limit, tombol watchlist.
- Halaman "Cara Kerja" + Syarat & Ketentuan.

### 5.2 Peserta (`/akun`)
- Registrasi, login, (opsional) 2FA, verifikasi email.
- Profil & KYC (upload KTP ke storage **privat**, NIK terenkripsi).
- Dashboard: bid aktif (menang/terlampaui), lot dimenangkan, invoice, watchlist.
- Invoice: detail, bayar via Midtrans Snap atau unggah bukti transfer.

### 5.3 Admin (`/admin`)
- Dashboard: lot live, bid hari ini, nilai terjual, invoice belum dibayar, settlement tertunda, KYC menunggu.
- **Penitip**: CRUD, rekening (terenkripsi), komisi default, daftar barang & settlement per penitip.
- **Barang titipan**: intake (kode otomatis `BRG-YYYY-00001`), inspeksi, foto (multi-upload, diubah ke WebP),
  atribut dinamis sesuai kategori, lokasi gudang, alur status.
- **Kategori**: nama + skema atribut (mis. Kendaraan: merk, tahun, nopol, km).
- **Sesi lelang**: jadwal, jaminan, premi pembeli, anti-sniping, penutupan bertahap; tambah lot dari barang `approved`.
- **Peserta**: verifikasi/penolakan KYC, blokir.
- **Invoice**: konfirmasi pembayaran manual, batalkan (wanprestasi).
- **Settlement**: tandai sudah ditransfer + unggah bukti.
- **Pengaturan**: tabel kelipatan, komisi & premi default, batas bayar invoice.
- **Log audit**: siapa melakukan apa, kapan, dari IP mana.

---

## 6. Arsitektur

```
app/
├── Http/Controllers/{Admin,Public,User}/     # sama seperti web-aspro
├── Http/Middleware/                          # HandleInertiaRequests, EnsureRole, EnsureTwoFactor
├── Http/Requests/                            # FormRequest validasi
├── Models/                                   # Eloquent + enum status
├── Enums/                                    # ItemStatus, LotStatus, InvoiceStatus, ...
├── Services/Auction/                         # ★ mesin lelang generik (reusable)
│   ├── BidIncrement.php                      #   tabel kelipatan
│   ├── BidService.php                        #   place(), proxy bid, anti-snipe, hash chain
│   └── LotCloser.php                         #   open/close lot, pemenang, hook ke invoice
├── Services/InvoiceService.php               # hitung premi/biaya, nomor invoice
├── Services/SettlementService.php            # hitung komisi → dana bersih penitip
├── Services/MidtransService.php              # pola sama dgn web-aspro (verifySignature)
├── Services/AuditLogger.php
├── Events/BidPlaced.php                      # ShouldBroadcast (Reverb/Pusher opsional)
└── Console: auctions:tick (dijadwalkan tiap menit)
resources/js/
├── Layouts/{Public,User,Admin}.vue
├── Components/                               # Countdown, LotCard, Money, StatusBadge, Pagination, FlashMessages
└── Pages/{Public,User,Admin,Auth}/
config/auction.php                            # semua aturan bisnis yang bisa diubah
```

**Real-time**: bid menyiarkan event `BidPlaced` (siap untuk Laravel Reverb). Tanpa server websocket,
halaman lot melakukan *polling* ringan ke endpoint JSON `/lot/{lot}/state` setiap 3–5 detik —
jadi tetap jalan di shared hosting seperti web-aspro.

---

## 7. Skema Database

| Tabel | Kolom utama |
|---|---|
| `users` | name, email, phone, role, nik (encrypted), ktp_path (private disk), kyc_status, kyc_note, kyc_verified_at, is_blocked, 2FA columns (Fortify) |
| `consignors` | code, name, phone, email, address, nik (encrypted), bank_name, bank_account (encrypted), bank_holder, commission_rate, notes |
| `categories` | name, slug, icon, attribute_schema (JSON) |
| `items` | code, consignor_id, category_id, title, slug, description, condition, specs (JSON), estimate_low/high, reserve_price, commission_rate (override), status, storage_location, received_at, inspected_by, inspection_notes |
| `item_images` | item_id, path, sort_order |
| `auctions` | code, title, slug, description, starts_at, ends_at, status, deposit_amount, buyer_premium_rate, anti_snipe_minutes, extend_minutes, stagger_seconds |
| `lots` | auction_id, item_id, lot_number, starting_price, reserve_price, current_price, bids_count, leader_id, winning_bid_id, starts_at, ends_at, extended_count, status, closed_at |
| `bids` | lot_id, user_id, amount, is_auto, ip, user_agent, prev_hash, hash, created_at |
| `auto_bids` | lot_id, user_id, max_amount, is_active |
| `auction_registrations` | auction_id, user_id, status (pending/approved/rejected), deposit_status, deposit_proof |
| `watchlists` | user_id, lot_id |
| `invoices` | number, user_id, lot_id, hammer_price, buyer_premium, admin_fee, total, status, due_at, paid_at, payment_method, payment_ref, payment_proof |
| `settlements` | number, consignor_id, lot_id, invoice_id, hammer_price, commission, other_fees, net_amount, status, paid_at, transfer_proof |
| `audit_logs` | user_id, action, subject_type, subject_id, properties (JSON), ip |
| ~~`settings`~~ | diganti `config/auction.php` pada MVP (lihat §15) |

Semua uang disimpan sebagai **BIGINT rupiah** (tanpa desimal) → tidak ada error pembulatan float.

---

## 8. Mesin Lelang (aturan inti)

**Menempatkan bid (`BidService::place`)** — semua dalam `DB::transaction` + `lockForUpdate()` pada baris lot:

1. Lot `live`, `now() (server) < ends_at`.
2. Peserta: login, tidak diblokir, **KYC verified**, terdaftar & disetujui pada sesi (bila sesi mensyaratkan jaminan).
3. Peserta bukan pemimpin saat ini (tidak menawar diri sendiri), bukan penitip barang itu.
4. `amount ≥ minimum_berikutnya` = `starting_price` (bid pertama) atau `current_price + increment(current_price)`,
   dan harus kelipatan yang sah; batas atas wajar (mis. ≤ 10× harga saat ini) untuk mencegah salah ketik.
5. Simpan bid dengan `hash = sha256(prev_hash | lot | user | amount | timestamp)` → riwayat tidak bisa diubah diam-diam.
6. **Proxy bid**: jika ada auto-bid lain yang lebih tinggi, sistem otomatis membalas sebesar
   `min(max_lawan, amount + increment)`; bila dua max sama, yang lebih dulu dipasang menang.
7. **Anti-sniping**: jika `ends_at − now < anti_snipe_minutes` → `ends_at += extend_minutes`.
8. Rate limit: maks. N bid/menit per pengguna (throttle), plus idempoten terhadap klik ganda
   (bid dengan nominal sama ditolak karena tidak lagi ≥ minimum).

**Penutupan (`auctions:tick`, tiap menit + dapat dipicu manual)**:
- Buka lot `scheduled` yang `starts_at ≤ now`.
- Tutup lot `live` yang `ends_at ≤ now` (lock baris): jika ada bid & `current_price ≥ reserve_price` → `sold`,
  buat invoice (jatuh tempo sesuai config), item `sold`; jika tidak → `unsold`, item kembali `approved`
  (bisa dilelang ulang) — keputusan dikembalikan ke penitip dicatat manual.
- Saat invoice `paid` → buat settlement penitip otomatis.

---

## 9. Keamanan (Checklist)

- [x] **Race condition bid**: transaksi + row lock; tes otomatis.
- [x] **Waktu server otoritatif**: countdown di klien dikalibrasi dengan `server_time`; validasi di server.
- [x] **Rate limiting** login, registrasi, bid.
- [x] **CSRF** (bawaan Laravel/Inertia), semua aksi ubah-state pakai POST/PUT/DELETE (bukan GET — pelajaran web-aspro).
- [x] **Otorisasi server-side**: middleware role + cek kepemilikan (invoice hanya milik peserta itu).
- [x] **2FA wajib** untuk `super_admin`/`admin` (Fortify), sama seperti web-aspro.
- [x] **Enkripsi kolom** NIK & rekening (`encrypted` cast); KTP & bukti transfer di **disk privat**,
      diakses lewat controller yang memeriksa izin.
- [x] **Validasi upload**: mime/ukuran, gambar di-*re-encode* (menghapus payload/EXIF).
- [x] **Audit log** semua aksi admin + hash chain bid.
- [x] **Webhook Midtrans**: verifikasi `signature_key` SHA-512 + cek nominal.
- [x] **Header keamanan**: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- [x] **Mass assignment**: `$fillable` eksplisit; role tidak bisa diisi dari form registrasi.
- [ ] Fase 2: reCAPTCHA/Turnstile di registrasi, deteksi *shill bidding* (penitip menawar barang sendiri via akun lain:
      cek IP/perangkat sama), notifikasi login baru, backup terenkripsi harian.

---

## 10. Desain & UX ("menarik")

- Tailwind CSS v4, palet hangat (amber/emas = nuansa lelang), font *Inter*.
- Kartu lot: foto 4:3, badge status (LIVE berkedip, "Segera", "Terjual"), harga saat ini tebal, countdown.
- Countdown berubah merah < 5 menit; banner "Waktu diperpanjang!" saat anti-sniping aktif.
- Tombol bid cepat (+1, +2, +5 kelipatan) supaya mobile nyaman.
- Konfirmasi bid via SweetAlert2 (sama dengan web-aspro) menampilkan total yang harus dibayar termasuk premi.
- Mobile-first; admin memakai layout sidebar sederhana dengan tabel + filter + pagination.

---

## 11. Manajemen Mudah

- **Satu layar per langkah** alur kerja, tombol aksi kontekstual sesuai status (mis. barang `inspected` hanya
  menampilkan "Setujui untuk lelang").
- **Nomor otomatis** untuk barang, sesi, invoice, settlement.
- **Aturan bisnis di `config/auction.php`** (+ override `.env`): kelipatan, komisi default, premi, batas bayar,
  anti-sniping. Tidak perlu ubah kode untuk menyesuaikan kebijakan.
- **Scheduler tunggal**: `php artisan schedule:work` / cron `* * * * * php artisan schedule:run`.
- Ekspor Excel (Fase 2, `maatwebsite/excel` seperti web-aspro) untuk laporan penjualan & settlement.

---

## 12. Reusabilitas — Menerapkan ke Kebutuhan Lain

Mesin lelang hanya bergantung pada tabel `lots`, `bids`, `auto_bids` dan antarmuka sederhana:
`Lot` punya harga awal, limit, waktu, dan relasi ke "sesuatu yang dilelang" (`item`).
Contoh adaptasi:

| Kasus | Perubahan yang diperlukan |
|---|---|
| Lelang kendaraan / properti | Tambah kategori dengan `attribute_schema` (merk, tahun, nopol / luas, SHM). Tanpa ubah kode. |
| Lelang barang sitaan/inventaris kantor | Penitip = unit kerja; komisi 0%. |
| Lelang amal / donasi | Premi 0%, settlement ke yayasan; tampilkan "dana terkumpul". |
| *Reverse auction* (pengadaan/tender) | Balik arah perbandingan di `BidIncrement`/`BidService` (penawaran terendah menang) — dirancang sebagai strategi. |
| Multi-cabang / multi-tenant | Tambah `tenant_id` + global scope; konfigurasi per tenant di tabel `settings`. |

---

## 13. Roadmap

| Fase | Lingkup | Estimasi* |
|---|---|---|
| **Fase 1 — MVP (dikerjakan di repo ini)** | Auth + 2FA admin, KYC, penitip, barang + foto, kategori dinamis, sesi & lot, bid + proxy + anti-snipe, scheduler penutupan, invoice, konfirmasi bayar manual, Midtrans Snap, settlement, audit log, dashboard, tes otomatis | 4–6 minggu |
| **Fase 2** | Notifikasi email/WA, jaminan online + refund, sealed bid, portal penitip, ekspor Excel, PDF invoice & berita acara, Reverb websockets, reCAPTCHA | 3–4 minggu |
| **Fase 3** | Buy now, live auction dengan juru lelang (streaming), aplikasi mobile (PWA), multi-tenant, analitik harga | berkelanjutan |

\* estimasi untuk 1–2 developer.

---

## 14. Pengujian & Deploy

- PHPUnit feature test: aturan bid, race/lock, proxy bid, anti-sniping, penutupan & invoice, settlement,
  otorisasi admin vs peserta, webhook Midtrans.
- CI GitHub Actions: `composer install`, `npm ci && npm run build`, `php artisan test`, `pint --test`.
- Deploy: PHP 8.3, MySQL 8 / MariaDB, `php artisan storage:link`, cron scheduler, queue worker
  (`php artisan queue:work`), HTTPS wajib, `APP_DEBUG=false`, backup DB harian.

---

## 15. Status Implementasi

**Fase 1 (MVP) sudah diimplementasikan di repo ini** — lihat `README.md` bagian *Fitur yang sudah diimplementasikan*.
Catatan penyesuaian dari rencana:

- Tabel `settings` diganti `config/auction.php` + `.env` (lebih sederhana; tabel settings dipindah ke Fase 2
  bila admin perlu mengubah aturan dari UI).
- Kolom atribut dinamis barang bernama `specs` (menghindari bentrok dengan properti internal Eloquent).
- Real-time memakai polling ringan + event `BidPlaced` yang siap disiarkan (aktifkan Reverb di Fase 2).
- Tes otomatis: 41 tes (mesin bid, proxy, anti-sniping, penutupan, invoice/settlement, otorisasi,
  webhook Midtrans, alur admin end-to-end).

**Fase 2 — sudah dikerjakan sebagian:**

- Notifikasi email + in-app (bid terlampaui, segera berakhir, menang, invoice lunas/batal, KYC, jaminan).
- Pembatalan otomatis invoice lewat jatuh tempo + jaminan pemenang otomatis hangus.
- Status uang jaminan (ditahan → dikembalikan/hangus) — pengembalian dana tetap ditransfer manual.
- PDF invoice & berita acara serah terima; laporan periode + ekspor Excel penjualan & settlement.
- Portal penitip read-only via *signed URL* yang kedaluwarsa dan bisa dicabut.
- Total 53 tes otomatis.

**Sisa Fase 2:** notifikasi WhatsApp (butuh penyedia WA API), refund jaminan otomatis via payment gateway,
sealed bid, Laravel Reverb (websocket), reCAPTCHA/Turnstile, deteksi *shill bidding* lanjutan.
**Fase 3:** buy now, live auction dengan juru lelang, PWA, multi-tenant, analitik harga.
