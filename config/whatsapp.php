<?php

/*
|--------------------------------------------------------------------------
| Notifikasi WhatsApp
|--------------------------------------------------------------------------
|
| WHATSAPP_DRIVER: kosong = nonaktif, "log" = hanya dicatat di log (lokal/uji),
| "fonnte" atau "wablas" = dikirim lewat penyedia tersebut. Penyedia lain cukup
| ditambah sebagai driver baru di app/WhatsApp/Drivers + didaftarkan di WhatsAppManager.
|
*/

return [
    'driver' => env('WHATSAPP_DRIVER'),

    // Kode negara untuk menormalkan nomor 08xx → 628xx.
    'country_code' => env('WHATSAPP_COUNTRY_CODE', '62'),

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
    ],

    'wablas' => [
        'token' => env('WABLAS_TOKEN'),
        'secret_key' => env('WABLAS_SECRET_KEY'),
        // Setiap akun Wablas punya server sendiri, mis. https://tegal.wablas.com
        'base_url' => env('WABLAS_BASE_URL'),
    ],
];
