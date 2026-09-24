<?php

namespace App\Enums;

enum Role: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Staff = 'staff';
    case Bidder = 'bidder';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Staff => 'Staf Gudang',
            self::Bidder => 'Peserta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'purple',
            self::Admin => 'indigo',
            self::Staff => 'sky',
            self::Bidder => 'gray',
        };
    }

    /** Peran yang boleh masuk panel admin. */
    public function isBackoffice(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Staff], true);
    }

    /** Peran yang wajib memakai 2FA. */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }
}
