<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Penitip / pemilik barang. */
class Consignor extends Model
{
    use HasFactory, HasSequentialCode, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'nik',
        'bank_name', 'bank_account', 'bank_holder', 'commission_rate', 'notes',
    ];

    protected $hidden = ['nik', 'bank_account', 'portal_nonce'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'bank_account' => 'encrypted',
            'commission_rate' => 'float',
        ];
    }

    public static function codePrefix(): string
    {
        return 'PNT';
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Link portal read-only untuk penitip. Ditandatangani (tidak bisa diubah) dan
     * kedaluwarsa; mengganti nonce mencabut semua link yang pernah dibagikan.
     */
    public function portalUrl(): string
    {
        if (! $this->portal_nonce) {
            $this->forceFill(['portal_nonce' => Str::random(40)])->saveQuietly();
        }

        return URL::temporarySignedRoute(
            'consignor.portal',
            now()->addDays((int) config('auction.consignor_portal_days', 30)),
            ['consignor' => $this->id, 'k' => $this->portal_nonce],
        );
    }

    public function resetPortalLink(): void
    {
        $this->forceFill(['portal_nonce' => Str::random(40)])->saveQuietly();
    }

    /** Nomor rekening disamarkan untuk tampilan, mis. ******7890. */
    public function maskedBankAccount(): ?string
    {
        if (! $this->bank_account) {
            return null;
        }

        return str_repeat('*', max(0, strlen($this->bank_account) - 4)).substr($this->bank_account, -4);
    }
}
