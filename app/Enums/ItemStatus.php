<?php

namespace App\Enums;

enum ItemStatus: string
{
    use HasOptions;

    case Received = 'received';
    case Inspected = 'inspected';
    case Approved = 'approved';
    case Listed = 'listed';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Delivered = 'delivered';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Diterima',
            self::Inspected => 'Diperiksa',
            self::Approved => 'Siap dilelang',
            self::Listed => 'Dalam lelang',
            self::Sold => 'Terjual',
            self::Unsold => 'Tidak laku',
            self::Delivered => 'Diserahkan',
            self::Returned => 'Dikembalikan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::Inspected => 'sky',
            self::Approved => 'indigo',
            self::Listed => 'amber',
            self::Sold => 'green',
            self::Unsold => 'orange',
            self::Delivered => 'emerald',
            self::Returned => 'slate',
        };
    }
}
