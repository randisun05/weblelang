<?php

namespace App\Enums;

enum SettlementStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu transfer',
            self::Paid => 'Sudah ditransfer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
        };
    }
}
