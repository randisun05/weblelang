<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
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
        'bank_name',
        'bank_account',
        'bank_holder',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'nik',
        'ktp_path',
        'bank_account',
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
            'terms_accepted_at' => 'datetime',
            'nik' => 'encrypted',
            'bank_account' => 'encrypted',
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

    /** Pengguna belum menyetujui versi S&K/Kebijakan Privasi yang berlaku saat ini. */
    public function needsTermsAcceptance(): bool
    {
        return ! $this->isBackoffice() && $this->terms_version !== config('legal.terms_version');
    }

    public function acceptTerms(): void
    {
        $this->forceFill(['terms_version' => config('legal.terms_version'), 'terms_accepted_at' => now()])->save();
    }

    public function hasBankAccount(): bool
    {
        return filled($this->bank_name) && filled($this->bank_account) && filled($this->bank_holder);
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
