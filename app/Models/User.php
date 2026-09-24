<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * `role`, `kyc_status` dan `is_blocked` sengaja TIDAK fillable
     * agar tidak bisa diubah lewat form (mass assignment).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'nik',
        'address',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'nik',
        'ktp_path',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'kyc_status' => KycStatus::class,
            'kyc_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_blocked' => 'boolean',
            'nik' => 'encrypted',
        ];
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function watchlist(): BelongsToMany
    {
        return $this->belongsToMany(Lot::class, 'watchlists')->withTimestamps();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(AuctionRegistration::class);
    }

    public function isBackoffice(): bool
    {
        return $this->role->isBackoffice();
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof Role ? $role : Role::from($role))) {
                return true;
            }
        }

        return false;
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === KycStatus::Verified;
    }

    /** Nama yang disamarkan untuk riwayat bid publik, mis. "Bud***42". */
    public function maskedName(): string
    {
        return Str::substr($this->name, 0, 3).'***'.str_pad((string) ($this->id % 100), 2, '0', STR_PAD_LEFT);
    }
}
