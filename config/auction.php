<?php

/*
|--------------------------------------------------------------------------
| Aturan bisnis lelang
|--------------------------------------------------------------------------
|
| Semua kebijakan lelang dikumpulkan di sini supaya bisa diubah tanpa
| menyentuh kode. Nilai uang dalam rupiah (integer).
|
*/

return [

    // Tabel kelipatan penawaran: [batas_bawah_harga => kelipatan].
    // Kelipatan yang berlaku = entri terakhir yang batas bawahnya <= harga saat ini.
    'increments' => [
        0 => 10_000,
        500_000 => 25_000,
        1_000_000 => 50_000,
        5_000_000 => 100_000,
        10_000_000 => 250_000,
        50_000_000 => 500_000,
        100_000_000 => 1_000_000,
        500_000_000 => 5_000_000,
    ],

    // Penawaran lebih dari X kali harga minimum dianggap salah ketik dan ditolak.
    'max_jump_multiplier' => 10,

    // Komisi default dari penitip (%) bila tidak diatur per penitip / per barang.
    'default_commission_rate' => (float) env('AUCTION_COMMISSION_RATE', 10),

    // Premi pembeli default (%) untuk sesi baru.
    'default_buyer_premium_rate' => (float) env('AUCTION_BUYER_PREMIUM_RATE', 5),

    // Biaya admin tetap per invoice.
    'admin_fee' => (int) env('AUCTION_ADMIN_FEE', 0),

    // Batas waktu pembayaran invoice (jam).
    'invoice_due_hours' => (int) env('AUCTION_INVOICE_DUE_HOURS', 72),

    // Anti-sniping default untuk sesi baru (menit).
    'anti_snipe_minutes' => 3,
    'extend_minutes' => 3,

    // Batas bid per pengguna per menit.
    'bid_rate_limit' => 20,

    // Wajib KYC terverifikasi sebelum bisa menawar.
    'require_kyc' => (bool) env('AUCTION_REQUIRE_KYC', true),

    // Rekening tujuan transfer manual pembayaran invoice / uang jaminan.
    'bank' => [
        'name' => env('PAYMENT_BANK_NAME', 'BCA'),
        'account' => env('PAYMENT_BANK_ACCOUNT', '0000000000'),
        'holder' => env('PAYMENT_BANK_HOLDER', env('APP_NAME', 'WebLelang')),
    ],

    // Interval polling halaman lot (detik) bila websocket tidak dipakai.
    'poll_seconds' => 4,
];
