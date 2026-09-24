<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Penitip / pemilik barang. */
class Consignor extends Model
{
    use HasFactory, HasSequentialCode, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'nik',
        'bank_name', 'bank_account', 'bank_holder', 'commission_rate', 'notes',
    ];

    protected $hidden = ['nik', 'bank_account'];

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

    /** Nomor rekening disamarkan untuk tampilan, mis. ******7890. */
    public function maskedBankAccount(): ?string
    {
        if (! $this->bank_account) {
            return null;
        }

        return str_repeat('*', max(0, strlen($this->bank_account) - 4)).substr($this->bank_account, -4);
    }
}
