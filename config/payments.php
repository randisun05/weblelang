<?php

/*
|--------------------------------------------------------------------------
| Payment gateway (multi-driver)
|--------------------------------------------------------------------------
|
| Driver tersedia: "midtrans" (Snap + Iris), "xendit" (Invoice + Disbursement),
| dan "simulator" (hanya untuk lokal/demo — otomatis ditolak di production).
| Kosongkan PAYMENT_GATEWAY untuk menonaktifkan pembayaran online
| (peserta tetap bisa transfer manual + unggah bukti).
|
*/

return [

    // Gateway untuk menerima pembayaran (invoice & uang jaminan).
    'gateway' => env('PAYMENT_GATEWAY', 'simulator'),

    // Gateway untuk mengirim uang (payout penitip & refund jaminan). Kosong = manual.
    'payout_gateway' => env('PAYOUT_GATEWAY', env('PAYMENT_GATEWAY', 'simulator')),

    // Masa berlaku link pembayaran (menit).
    'checkout_expiry_minutes' => (int) env('PAYMENT_CHECKOUT_EXPIRY', 24 * 60),

    // Kembalikan uang jaminan otomatis setelah sesi selesai (peserta kalah, atau menang & sudah lunas).
    'auto_refund_deposits' => (bool) env('PAYMENT_AUTO_REFUND_DEPOSITS', true),

    'drivers' => [
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
            // Iris (disbursement): API key "creator" untuk membuat payout, "approver" untuk menyetujui.
            'iris_creator_key' => env('MIDTRANS_IRIS_CREATOR_KEY'),
            'iris_approver_key' => env('MIDTRANS_IRIS_APPROVER_KEY'),
            // Merchant key untuk memverifikasi header Iris-Signature pada notifikasi payout.
            'iris_merchant_key' => env('MIDTRANS_IRIS_MERCHANT_KEY'),
        ],

        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            // Verification token dari dashboard Xendit (header x-callback-token).
            'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        ],

        'simulator' => [],
    ],

    /*
    | Kode bank yang didukung untuk payout. Kunci = kode internal (disimpan di DB),
    | nilai = nama tampilan. Tiap driver memetakan kode ini ke format gateway-nya.
    | Cocokkan dengan daftar kode bank di dashboard gateway sebelum go-live.
    */
    'banks' => [
        'BCA' => 'BCA',
        'BNI' => 'BNI',
        'BRI' => 'BRI',
        'MANDIRI' => 'Mandiri',
        'BSI' => 'Bank Syariah Indonesia',
        'CIMB' => 'CIMB Niaga',
        'PERMATA' => 'Permata',
        'DANAMON' => 'Danamon',
        'BTN' => 'BTN',
    ],
];
