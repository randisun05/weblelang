<?php

namespace App\Models;

use App\Enums\AuctionMethod;
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
        'method', 'stream_url',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AuctionStatus::class,
            'method' => AuctionMethod::class,
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
            $auction->method ??= AuctionMethod::Open;
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

    public function isSealed(): bool
    {
        return $this->method === AuctionMethod::Sealed;
    }

    public function isLive(): bool
    {
        return $this->method === AuctionMethod::Live;
    }

    /**
     * URL embed yang aman untuk siaran (hanya YouTube & Vimeo). Selain itu null →
     * frontend cukup menampilkan tautan biasa, bukan iframe dari domain sembarang.
     */
    public function streamEmbedUrl(): ?string
    {
        $url = (string) $this->stream_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1';
        }

        if (preg_match('~vimeo\.com/(?:event/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    public function isPublic(): bool
    {
        return $this->status !== AuctionStatus::Draft;
    }
}
