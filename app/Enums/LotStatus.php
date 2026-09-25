<?php

namespace App\Enums;

enum LotStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case Live = 'live';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Segera',
            self::Live => 'Live',
            self::Sold => 'Terjual',
            self::Unsold => 'Tidak laku',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'sky',
            self::Live => 'red',
            self::Sold => 'green',
            self::Unsold => 'orange',
            self::Cancelled => 'slate',
        };
    }
}
