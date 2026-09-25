<?php

namespace App\Enums;

enum DepositStatus: string
{
    use HasOptions;

    case Held = 'held';
    case Refunded = 'refunded';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Jaminan ditahan',
            self::Refunded => 'Jaminan dikembalikan',
            self::Forfeited => 'Jaminan hangus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Held => 'sky',
            self::Refunded => 'green',
            self::Forfeited => 'red',
        };
    }
}
