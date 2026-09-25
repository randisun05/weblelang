<?php

namespace App\Models;

use App\Enums\LotStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id', 'item_id', 'lot_number', 'starting_price', 'reserve_price', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LotStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'ending_notified_at' => 'datetime',
            'starting_price' => 'integer',
            'reserve_price' => 'integer',
            'current_price' => 'integer',
            'bids_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lot $lot) {
            $lot->status ??= LotStatus::Scheduled;
        });
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->orderByDesc('amount')->orderBy('id');
    }

    public function autoBids(): HasMany
    {
        return $this->hasMany(AutoBid::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'watchlists')->withTimestamps();
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereHas('auction', fn ($q) => $q->where('status', '!=', 'draft'));
    }

    public function isLive(): bool
    {
        return $this->status === LotStatus::Live && $this->ends_at->isFuture();
    }

    public function reserveMet(): bool
    {
        return $this->bids_count > 0 && $this->current_price >= $this->reserve_price;
    }
}
