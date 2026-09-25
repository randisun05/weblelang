<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Penyetoran hasil lelang ke penitip. */
class Settlement extends Model
{
    use HasSequentialCode;

    protected $fillable = [
        'consignor_id', 'invoice_id', 'hammer_price', 'commission_rate', 'commission', 'other_fees', 'net_amount',
    ];

    protected $hidden = ['transfer_proof'];

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'paid_at' => 'datetime',
            'hammer_price' => 'integer',
            'commission_rate' => 'float',
            'commission' => 'integer',
            'other_fees' => 'integer',
            'net_amount' => 'integer',
        ];
    }

    public static function codePrefix(): string
    {
        return 'STL';
    }

    public static function codeColumn(): string
    {
        return 'number';
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(Consignor::class)->withTrashed();
    }

    public function payouts(): MorphMany
    {
        return $this->morphMany(Payout::class, 'payable')->latest('id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
