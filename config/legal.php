<?php

/*
|--------------------------------------------------------------------------
| Identitas penyelenggara & versi dokumen hukum
|--------------------------------------------------------------------------
|
| Ditampilkan di halaman Syarat & Ketentuan dan Kebijakan Privasi.
| Setiap kali isi dokumen berubah secara material, NAIKKAN versinya:
| pengguna lama akan diminta menyetujui ulang saat login berikutnya.
|
*/

return [
    'operator' => [
        'name' => env('LEGAL_OPERATOR_NAME', env('APP_NAME', 'WebLelang')),
        'legal_entity' => env('LEGAL_ENTITY', 'PT [Nama Badan Usaha]'),
        'address' => env('LEGAL_ADDRESS', '[Alamat kantor penyelenggara]'),
        'city' => env('LEGAL_CITY', '[Kota]'),
        'email' => env('LEGAL_EMAIL', 'halo@contoh.test'),
        'phone' => env('LEGAL_PHONE', '[Nomor telepon/WhatsApp]'),
        'nib' => env('LEGAL_NIB'),
        // Isi bila lelang diselenggarakan bekerja sama dengan Balai Lelang berizin.
        'auction_house' => env('LEGAL_AUCTION_HOUSE'),
    ],

    // Kontak pelindungan data pribadi (UU No. 27 Tahun 2022).
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', env('LEGAL_EMAIL', 'privasi@contoh.test')),

    // Batas waktu pengambilan barang setelah lunas (hari) sebelum dikenai biaya simpan.
    'pickup_days' => (int) env('LEGAL_PICKUP_DAYS', 7),

    'terms_version' => '2026-09-25',
    'privacy_version' => '2026-09-26',
];
