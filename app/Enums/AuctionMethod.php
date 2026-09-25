<?php

namespace App\Enums;

enum AuctionMethod: string
{
    use HasOptions;

    case Open = 'open';
    case Sealed = 'sealed';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Lelang terbuka',
            self::Sealed => 'Penawaran tertutup',
            self::Live => 'Live juru lelang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Sealed => 'purple',
            self::Live => 'red',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Open => 'Harga naik terbuka, lot tutup otomatis sesuai jadwal. Mendukung auto-bid, anti-sniping, dan Beli Langsung.',
            self::Sealed => 'Penawaran tidak terlihat siapa pun (termasuk admin) sampai lot ditutup. Peserta boleh mengubah penawaran sebelum tenggat; tertinggi menang.',
            self::Live => 'Juru lelang membuka lot satu per satu dari konsol, memanggil "pertama… kedua… terjual!", dengan siaran video.',
        };
    }
}
