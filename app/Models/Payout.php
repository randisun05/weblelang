<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/** Transaksi uang keluar lewat disbursement (payout penitip / refund jaminan). */
class Payout extends Model
{
    protected $fillable = ['gateway', 'amount', 'bank_code', 'account_number', 'account_holder', 'requested_by'];

    protected $hidden = ['account_number', 'payload'];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'integer',
            'account_number' => 'encrypted',
            'payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payout $payout) {
            $payout->reference ??= 'PO-'.Str::upper((string) Str::ulid());
            $payout->status ??= PayoutStatus::Pending;
        });
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function maskedAccount(): string
    {
        return str_repeat('*', max(0, strlen($this->account_number) - 4)).substr($this->account_number, -4);
    }
}
