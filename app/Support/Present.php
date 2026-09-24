<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Lot;
use App\Services\Auction\BidIncrement;

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

    public static function lotCard(Lot $lot): array
    {
        return [
            'id' => $lot->id,
            'lot_number' => $lot->lot_number,
            'title' => $lot->item->title,
            'category' => $lot->item->category?->name,
            'image' => $lot->item->images->first()?->url(),
            'starting_price' => $lot->starting_price,
            'current_price' => $lot->bids_count ? $lot->current_price : $lot->starting_price,
            'bids_count' => $lot->bids_count,
            'starts_at' => $lot->starts_at->toIso8601String(),
            'ends_at' => $lot->ends_at->toIso8601String(),
            'status' => self::status($lot->status),
        ];
    }

    public static function lotState(Lot $lot, ?int $viewerId = null): array
    {
        $increments = app(BidIncrement::class);

        return [
            'status' => self::status($lot->status),
            'current_price' => $lot->bids_count ? $lot->current_price : $lot->starting_price,
            'bids_count' => $lot->bids_count,
            'minimum_bid' => $increments->minimumNextBid($lot),
            'increment' => $increments->for($lot->bids_count ? $lot->current_price : $lot->starting_price),
            'reserve_met' => $lot->reserve_price <= 0 || $lot->reserveMet(),
            'has_reserve' => $lot->reserve_price > 0,
            'starts_at' => $lot->starts_at->toIso8601String(),
            'ends_at' => $lot->ends_at->toIso8601String(),
            'extended_count' => $lot->extended_count,
            'is_leader' => $viewerId !== null && $lot->leader_id === $viewerId,
            'server_time' => now()->toIso8601String(),
            'bids' => $lot->bids()->with('user:id,name')->limit(15)->get()->map(fn ($bid) => [
                'id' => $bid->id,
                'bidder' => $bid->user_id === $viewerId ? 'Anda' : $bid->user->maskedName(),
                'amount' => $bid->amount,
                'is_auto' => $bid->is_auto,
                'at' => $bid->created_at->toIso8601String(),
            ]),
        ];
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
