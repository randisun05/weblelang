<?php

namespace App\Enums;

enum AuctionStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Live = 'live';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terjadwal',
            self::Live => 'Berlangsung',
            self::Closed => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'sky',
            self::Live => 'red',
            self::Closed => 'slate',
        };
    }
}
