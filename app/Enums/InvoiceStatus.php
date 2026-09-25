<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum dibayar',
            self::Paid => 'Lunas',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'amber',
            self::Paid => 'green',
            self::Cancelled => 'slate',
        };
    }
}
