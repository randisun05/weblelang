<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Barang titipan. */
class Item extends Model
{
    use HasFactory, HasSequentialCode, SoftDeletes;

    public const CONDITIONS = [
        'baru' => 'Baru',
        'seperti_baru' => 'Seperti baru',
        'bekas_baik' => 'Bekas - baik',
        'bekas_cukup' => 'Bekas - cukup',
        'perlu_perbaikan' => 'Perlu perbaikan',
    ];

    /**
     * Transisi status yang diizinkan: [dari => [ke, ...]].
     * Status `listed`, `sold`, `delivered` diatur otomatis oleh sistem lelang.
     */
    public const TRANSITIONS = [
        'received' => ['inspected', 'returned'],
        'inspected' => ['approved', 'returned'],
        'approved' => ['returned'],
        'unsold' => ['approved', 'returned'],
    ];

    protected $fillable = [
        'consignor_id', 'category_id', 'title', 'description', 'condition', 'specs',
        'estimate_low', 'estimate_high', 'reserve_price', 'commission_rate',
        'storage_location', 'received_at', 'inspection_notes',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'status' => ItemStatus::class,
            'received_at' => 'datetime',
            'estimate_low' => 'integer',
            'estimate_high' => 'integer',
            'reserve_price' => 'integer',
            'commission_rate' => 'float',
        ];
    }

    public static function codePrefix(): string
    {
        return 'BRG';
    }

    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            $item->slug ??= Str::slug(Str::limit($item->title, 60, '')).'-'.Str::lower(Str::random(6));
            $item->status ??= ItemStatus::Received;
        });
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(Consignor::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('sort_order');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function currentLot(): HasOne
    {
        return $this->hasOne(Lot::class)->latestOfMany();
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /** Komisi yang berlaku: per barang → per penitip → default. */
    public function effectiveCommissionRate(): float
    {
        return $this->commission_rate
            ?? $this->consignor?->commission_rate
            ?? (float) config('auction.default_commission_rate');
    }

    public function canTransitionTo(ItemStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$this->status->value] ?? [], true);
    }

    /** Barang hanya bisa diubah datanya selama belum masuk lelang. */
    public function isEditable(): bool
    {
        return in_array($this->status, [ItemStatus::Received, ItemStatus::Inspected, ItemStatus::Approved, ItemStatus::Unsold], true);
    }

    public function coverUrl(): ?string
    {
        return $this->images->first()?->url();
    }
}
