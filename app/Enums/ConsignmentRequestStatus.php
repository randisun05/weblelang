<?php

namespace App\Enums;

enum ConsignmentRequestStatus: string
{
    use HasOptions;

    case New = 'new';
    case Reviewing = 'reviewing';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Reviewing => 'Sedang ditinjau',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'amber',
            self::Reviewing => 'sky',
            self::Accepted => 'green',
            self::Rejected => 'red',
        };
    }
}
