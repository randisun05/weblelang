<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasSequentialCode;

    protected $fillable = [
        'user_id', 'lot_id', 'hammer_price', 'buyer_premium', 'admin_fee', 'total', 'due_at',
    ];

    protected $hidden = ['payment_proof'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'hammer_price' => 'integer',
            'buyer_premium' => 'integer',
            'admin_fee' => 'integer',
            'total' => 'integer',
        ];
    }

    public static function codePrefix(): string
    {
        return 'INV';
    }

    public static function codeColumn(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable')->latest('id');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }
}
