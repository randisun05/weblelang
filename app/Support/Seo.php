<?php

namespace App\Support;

use App\Enums\AuctionMethod;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Lot;
use Illuminate\Support\Str;

/**
 * Meta tag halaman untuk mesin pencari & preview berbagi (WhatsApp, Facebook, X).
 * Diisi controller lewat Seo::set(...), dirender server-side oleh app.blade.php
 * (crawler & bot preview tidak menjalankan JavaScript). Satu instance per request.
 */
class Seo
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $image = null;

    public string $type = 'website';

    public bool $noindex = false;

    /** @var array<int, array<string, mixed>> */
    public array $jsonLd = [];

    public static function set(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $type = null,
        array $jsonLd = [],
        bool $noindex = false,
    ): self {
        $seo = app(self::class);
        $seo->title = $title ?? $seo->title;
        $seo->description = $description !== null ? self::clean($description) : $seo->description;
        $seo->image = $image ?? $seo->image;
        $seo->type = $type ?? $seo->type;
        $seo->noindex = $noindex || $seo->noindex;
        if ($jsonLd) {
            $seo->jsonLd[] = $jsonLd;
        }

        return $seo;
    }

    public function fullTitle(): string
    {
        $app = config('app.name');

        return $this->title ? "{$this->title} — {$app}" : $app;
    }

    public function metaDescription(): string
    {
        return $this->description ?? 'Lelang online barang titipan: barang diperiksa petugas, penawaran transparan, pembayaran aman.';
    }

    public function imageUrl(): string
    {
        return self::absolute($this->image ?? asset('og-default.png'));
    }

    public function canonical(): string
    {
        // Tanpa query string (filter/halaman) supaya tidak ada duplikat konten di indeks.
        return url()->current();
    }

    public function jsonLdScripts(): array
    {
        return array_map(
            fn (array $data) => json_encode(['@context' => 'https://schema.org'] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
            $this->jsonLd,
        );
    }

    public static function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    public static function clean(string $text, int $limit = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($text))), $limit);
    }

    // ---- Builder per jenis halaman ------------------------------------------

    public static function home(): self
    {
        return self::set(
            title: 'Lelang Online Barang Titipan',
            jsonLd: [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => url('/'),
                'publisher' => self::organization(),
            ],
        );
    }

    /**
     * Lot sebagai Product + Offer. Harga yang dipakai sama dengan yang tampil di halaman
     * (Present::visiblePrice): lelang tertutup hanya harga awal, reserve price tidak pernah keluar.
     */
    public static function lot(Lot $lot): self
    {
        $item = $lot->item;
        $price = Present::visiblePrice($lot);
        $images = $item->images->map(fn ($image) => self::absolute($image->url()))->values()->all();
        $method = $lot->method();

        $priceText = match (true) {
            $lot->status === LotStatus::Sold && $method !== AuctionMethod::Sealed => 'Terjual Rp '.number_format($lot->current_price, 0, ',', '.'),
            $method === AuctionMethod::Sealed || ! $lot->bids_count => 'Harga awal Rp '.number_format($lot->starting_price, 0, ',', '.'),
            default => 'Tawaran saat ini Rp '.number_format($price, 0, ',', '.'),
        };

        $availability = match ($lot->status) {
            LotStatus::Live => 'InStock',
            LotStatus::Scheduled => 'PreOrder',
            LotStatus::Sold => 'SoldOut',
            default => 'Discontinued',
        };

        return self::set(
            title: "Lot {$lot->lot_number}: {$item->title}",
            description: "{$priceText} · {$lot->auction->title}. ".self::clean((string) $item->description, 110),
            image: $images[0] ?? null,
            type: 'product',
            jsonLd: array_filter([
                '@type' => 'Product',
                'name' => $item->title,
                'description' => self::clean((string) $item->description, 500),
                'sku' => $item->code,
                'category' => $item->category?->name,
                'image' => $images ?: null,
                'itemCondition' => 'https://schema.org/'.($item->condition === 'baru' ? 'NewCondition' : 'UsedCondition'),
                'offers' => [
                    '@type' => 'Offer',
                    'url' => route('lots.show', $lot),
                    'priceCurrency' => 'IDR',
                    'price' => $price,
                    'availability' => 'https://schema.org/'.$availability,
                    'priceValidUntil' => $lot->ends_at->toDateString(),
                    'seller' => self::organization(),
                ],
            ]),
        );
    }

    public static function auction(Auction $auction, ?string $image = null): self
    {
        return self::set(
            title: $auction->title,
            description: $auction->method->label().' · '.$auction->starts_at->translatedFormat('j M Y H:i').' WIB. '
                .self::clean((string) $auction->description, 110),
            image: $image,
            jsonLd: [
                '@type' => 'Event',
                'name' => $auction->title,
                'description' => self::clean((string) $auction->description, 500),
                'startDate' => $auction->starts_at->toIso8601String(),
                'endDate' => $auction->ends_at->toIso8601String(),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
                'location' => ['@type' => 'VirtualLocation', 'url' => route('auctions.show', $auction->slug)],
                'organizer' => self::organization(),
                'image' => $image ? [self::absolute($image)] : [self::absolute(asset('og-default.png'))],
            ],
        );
    }

    public static function organization(): array
    {
        // Nilai contoh "[...]" di config/legal.php belum diisi → jangan ikut dipublikasikan.
        $filled = fn ($value) => is_string($value) && $value !== '' && ! str_starts_with($value, '[');

        return array_filter([
            '@type' => 'Organization',
            'name' => config('legal.operator.name') ?: config('app.name'),
            'url' => url('/'),
            'email' => config('legal.operator.email'),
            'telephone' => config('legal.operator.phone'),
        ], $filled);
    }
}
