<?php

namespace App\Support;

use App\Enums\AuctionMethod;
use App\Models\Bid;
use App\Models\Item;
use App\Models\Lot;
use App\Services\Auction\BidIncrement;
use Illuminate\Support\Collection;

/**
 * Mengubah model menjadi array untuk props Inertia. Hanya field yang aman
 * untuk publik yang dikirim (mis. harga limit TIDAK pernah dikirim ke peserta).
 */
class Present
{
    public static function status(\BackedEnum $status): array
    {
        return ['value' => $status->value, 'label' => $status->label(), 'color' => $status->color()];
    }

    /** Harga yang boleh ditampilkan: lot tertutup selalu menampilkan harga awal. */
    public static function visiblePrice(Lot $lot): int
    {
        return ! $lot->isConcealed() && $lot->bids_count && $lot->current_price ? $lot->current_price : $lot->starting_price;
    }

    public static function lotCard(Lot $lot): array
    {
        return [
            'id' => $lot->id,
            'lot_number' => $lot->lot_number,
            'title' => $lot->item->title,
            'category' => $lot->item->category?->name,
            'image' => $lot->item->images->first()?->url(),
            'starting_price' => $lot->starting_price,
            'current_price' => self::visiblePrice($lot),
            'bids_count' => $lot->isConcealed() ? null : $lot->bids_count,
            'buy_now_price' => $lot->buyNowAvailable() ? $lot->buy_now_price : null,
            'method' => self::status($lot->method()),
            'starts_at' => $lot->starts_at->toIso8601String(),
            'ends_at' => $lot->ends_at->toIso8601String(),
            'status' => self::status($lot->status),
        ];
    }

    public static function lotState(Lot $lot, ?int $viewerId = null): array
    {
        $increments = app(BidIncrement::class);
        $concealed = $lot->isConcealed();
        $sealed = $lot->method() === AuctionMethod::Sealed;
        $live = $lot->method() === AuctionMethod::Live;

        return [
            'status' => self::status($lot->status),
            'method' => self::status($lot->method()),
            'concealed' => $concealed,
            'current_price' => self::visiblePrice($lot),
            'bids_count' => $concealed ? null : $lot->bids_count,
            'minimum_bid' => $sealed ? $lot->starting_price : $increments->minimumNextBid($lot),
            'increment' => $sealed ? null : $increments->for(self::visiblePrice($lot)),
            'reserve_met' => $concealed ? null : ($lot->reserve_price <= 0 || $lot->reserveMet()),
            'has_reserve' => $lot->reserve_price > 0,
            'buy_now_price' => $lot->buyNowAvailable() ? $lot->buy_now_price : null,
            'sold_via' => $lot->sold_via,
            'live_calls' => $live ? $lot->live_calls : null,
            'my_bid' => $sealed && $viewerId
                ? Bid::where('lot_id', $lot->id)->where('user_id', $viewerId)->latest('id')->value('amount')
                : null,
            'starts_at' => $lot->starts_at->toIso8601String(),
            'ends_at' => $lot->ends_at->toIso8601String(),
            'extended_count' => $lot->extended_count,
            'is_leader' => ! $concealed && $viewerId !== null && $lot->leader_id === $viewerId,
            'server_time' => now()->toIso8601String(),
            'bids' => $concealed ? [] : self::bidHistory($lot, $viewerId, $sealed),
        ];
    }

    /** Riwayat publik; untuk lelang tertutup (setelah dibuka) hanya penawaran final tiap peserta. */
    private static function bidHistory(Lot $lot, ?int $viewerId, bool $finalOnly): Collection
    {
        $query = Bid::with('user:id,name')->where('lot_id', $lot->id)->orderByDesc('amount')->orderBy('id')->limit(15);

        if ($finalOnly) {
            $query->whereIn('id', Bid::where('lot_id', $lot->id)->selectRaw('max(id)')->groupBy('user_id'));
        }

        return $query->get()->map(fn (Bid $bid) => [
            'id' => $bid->id,
            'bidder' => $bid->user_id === $viewerId ? 'Anda' : $bid->user->maskedName(),
            'amount' => $bid->amount,
            'is_auto' => $bid->is_auto,
            'at' => $bid->created_at->toIso8601String(),
        ]);
    }

    public static function itemAttributes(Item $item): array
    {
        $schema = collect($item->category?->attribute_schema ?? []);
        $values = $item->specs ?? [];

        return $schema
            ->filter(fn ($field) => filled($values[$field['key']] ?? null))
            ->map(fn ($field) => ['label' => $field['label'], 'value' => $values[$field['key']]])
            ->values()
            ->all();
    }
}
