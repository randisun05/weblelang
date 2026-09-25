<?php

namespace App\Enums;

enum PayoutStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Dibuat',
            self::Processing => 'Diproses bank',
            self::Completed => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'sky',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Failed;
    }
}
