<?php

namespace App\Models;

use App\Enums\AuctionMethod;
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
        'auction_id', 'item_id', 'lot_number', 'starting_price', 'reserve_price', 'buy_now_price', 'starts_at', 'ends_at',
    ];

    /** Metode lelang (terbuka/tertutup/live) menentukan perilaku lot, jadi sesi selalu dimuat. */
    protected $with = ['auction'];

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
            'buy_now_price' => 'integer',
            'live_calls' => 'integer',
            'live_called_at' => 'datetime',
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

    public function method(): AuctionMethod
    {
        // Jika sesi dimuat dengan kolom terbatas, ambil ulang — jangan pernah menebak
        // "terbuka" karena lot tertutup bisa bocor.
        return $this->auction->method
            ?? AuctionMethod::from((string) Auction::whereKey($this->auction_id)->value('method'));
    }

    /**
     * Lot penawaran tertutup yang belum ditutup: nominal, jumlah, dan pemimpin
     * TIDAK boleh ditampilkan kepada siapa pun, termasuk admin.
     */
    public function isConcealed(): bool
    {
        return $this->method() === AuctionMethod::Sealed
            && in_array($this->status, [LotStatus::Scheduled, LotStatus::Live], true);
    }

    /** Tombol Beli Langsung hanya untuk lelang terbuka, lot aktif, dan sebelum ada penawaran. */
    public function buyNowAvailable(): bool
    {
        return $this->buy_now_price !== null
            && $this->method() === AuctionMethod::Open
            && $this->status === LotStatus::Live
            && $this->bids_count === 0
            && $this->ends_at->isFuture();
    }

    public function reserveMet(): bool
    {
        return $this->bids_count > 0 && $this->current_price >= $this->reserve_price;
    }
}
