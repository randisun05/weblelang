<?php

namespace App\Enums;

enum KycStatus: string
{
    use HasOptions;

    case Unsubmitted = 'unsubmitted';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unsubmitted => 'Belum diajukan',
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unsubmitted => 'gray',
            self::Pending => 'amber',
            self::Verified => 'green',
            self::Rejected => 'red',
        };
    }
}
