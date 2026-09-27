# Panduan Uji Payment Gateway (Sandbox)

Lakukan ini **sebelum go-live**. Semua tes otomatis memakai respons gateway tiruan, jadi integrasi dengan
akun gateway sungguhan harus diuji manual sekali di sandbox.

## 1. Siapkan URL publik HTTPS

Gateway harus bisa memanggil webhook aplikasi. Untuk uji dari laptop, pakai tunnel:

```bash
php artisan serve --port=8000
cloudflared tunnel --url http://localhost:8000     # atau: ngrok http 8000
```

Isi `APP_URL` dengan URL HTTPS dari tunnel, lalu `php artisan config:clear`.

## 2. Isi kredensial sandbox di `.env`

**Midtrans** (Dashboard sandbox → Settings → Access Keys; Iris dari dashboard Iris sandbox):
```
PAYMENT_GATEWAY=midtrans
PAYOUT_GATEWAY=midtrans
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_IRIS_CREATOR_KEY=...
MIDTRANS_IRIS_APPROVER_KEY=...      # opsional: kosong = setujui manual di dashboard Iris
MIDTRANS_IRIS_MERCHANT_KEY=...
```

**Xendit** (Dashboard mode Test → Settings → API Keys & Webhooks):
```
PAYMENT_GATEWAY=xendit
PAYOUT_GATEWAY=xendit
XENDIT_SECRET_KEY=xnd_development_xxxx     # izin: Money-in (Invoice) & Money-out (Disbursement)
XENDIT_CALLBACK_TOKEN=...                  # "Webhook verification token"
```

Boleh dicampur, mis. `PAYMENT_GATEWAY=midtrans` dan `PAYOUT_GATEWAY=xendit`.

## 3. Jalankan pemeriksaan otomatis

```bash
php artisan payments:check
```

Perintah ini **tidak membuat transaksi**. Ia memeriksa kunci API lewat endpoint baca-saja, mencocokkan kode bank
di `config/payments.php` dengan daftar bank gateway, lalu menampilkan URL webhook yang harus didaftarkan.
Perbaiki semua tanda ✗ sebelum lanjut.

## 4. Daftarkan URL webhook

| Gateway | Menu di dashboard | URL |
|---|---|---|
| Midtrans | Settings → Configuration → Payment Notification URL | `https://DOMAIN/payments/webhook/midtrans` |
| Midtrans Iris | Iris → Settings → Callback URL | `https://DOMAIN/payouts/webhook/midtrans` |
| Xendit | Settings → Webhooks → Invoices paid | `https://DOMAIN/payments/webhook/xendit` |
| Xendit | Settings → Webhooks → Disbursement | `https://DOMAIN/payouts/webhook/xendit` |

## 5. Skenario uji manual (centang semua)

Jalankan juga `php artisan queue:work` dan `php artisan schedule:work` di terminal terpisah.

- [ ] **Bayar invoice**: menangkan satu lot → Akun → Invoice → *Bayar online* → selesaikan di halaman sandbox
      (Midtrans: pakai simulator pembayaran sandbox; Xendit: tombol simulasi bayar di invoice mode test)
      → status invoice **Lunas**, settlement penitip terbuat, notifikasi masuk.
- [ ] **Pembayaran kedaluwarsa/gagal**: buat checkout lalu biarkan kedaluwarsa atau batalkan → status pembayaran
      **Kedaluwarsa/Gagal**, invoice tetap belum dibayar.
- [ ] **Setor jaminan**: sesi dengan jaminan → *Bayar jaminan online* → pendaftaran otomatis **Disetujui**.
- [ ] **Payout penitip**: Admin → Settlement → *Transfer via gateway* → status **Diproses bank** lalu **Terkirim**
      setelah webhook masuk (di Iris sandbox, setujui payout bila approver key kosong).
- [ ] **Refund jaminan otomatis**: tutup sesi berjaminan → peserta kalah yang sudah mengisi rekening menerima refund.
- [ ] **Payout gagal**: pakai nomor rekening tidak valid (lihat dokumentasi sandbox gateway untuk nomor uji) →
      status **Gagal** dengan alasan, lalu admin bisa *Coba transfer lagi* setelah rekening diperbaiki.
- [ ] Cek menu **Transaksi Gateway** dan **Log Audit** mencatat semua langkah di atas.

## 6. Pindah ke production

1. Ganti ke kunci production, set `MIDTRANS_IS_PRODUCTION=true` (Midtrans) / kunci `xnd_production_...` (Xendit).
2. Daftarkan ulang URL webhook di dashboard production.
3. Jalankan `php artisan payments:check` sekali lagi di server production.
4. Lakukan satu transaksi nyata bernilai kecil, lalu refund/payout untuk memastikan alur uang keluar.
