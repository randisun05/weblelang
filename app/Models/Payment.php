<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/** Transaksi uang masuk lewat payment gateway. */
class Payment extends Model
{
    protected $fillable = ['gateway', 'amount', 'user_id', 'expires_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'payload' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            // Referensi unik & tidak bisa ditebak; dipakai sebagai order_id / external_id di gateway.
            $payment->reference ??= 'PAY-'.Str::upper((string) Str::ulid());
            $payment->status ??= PaymentStatus::Pending;
        });
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Uraian singkat untuk halaman checkout gateway. */
    public function description(): string
    {
        return match (true) {
            $this->payable instanceof Invoice => 'Pembayaran '.$this->payable->number,
            $this->payable instanceof AuctionRegistration => 'Uang jaminan lelang '.$this->payable->auction->title,
            default => 'Pembayaran '.$this->reference,
        };
    }

    /** Halaman tujuan setelah peserta selesai di halaman gateway. */
    public function returnUrl(): string
    {
        return route('payments.show', $this->reference);
    }

    public function isReusable(): bool
    {
        return $this->status === PaymentStatus::Pending && $this->checkout_url && $this->expires_at?->isFuture();
    }
}
