<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Sesi lelang yang berisi beberapa lot. */
class Auction extends Model
{
    use HasFactory, HasSequentialCode;

    protected $fillable = [
        'title', 'description', 'starts_at', 'ends_at', 'deposit_amount',
        'buyer_premium_rate', 'anti_snipe_minutes', 'extend_minutes', 'stagger_seconds',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AuctionStatus::class,
            'deposit_amount' => 'integer',
            'buyer_premium_rate' => 'float',
        ];
    }

    public static function codePrefix(): string
    {
        return 'LLG';
    }

    protected static function booted(): void
    {
        static::creating(function (Auction $auction) {
            $auction->slug ??= Str::slug(Str::limit($auction->title, 60, '')).'-'.Str::lower(Str::random(5));
            $auction->status ??= AuctionStatus::Draft;
        });
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class)->orderBy('lot_number');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(AuctionRegistration::class);
    }

    public function requiresRegistration(): bool
    {
        return $this->deposit_amount > 0;
    }

    public function isPublic(): bool
    {
        return $this->status !== AuctionStatus::Draft;
    }
}
