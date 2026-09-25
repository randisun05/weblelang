<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Services\Auction\BidException;
use App\Services\Auction\BidIncrement;
use App\Services\Auction\LotCloser;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Konsol juru lelang untuk sesi bermetode "live". */
class LiveAuctionController extends Controller
{
    public function show(Auction $auction, BidIncrement $increments): Response
    {
        abort_unless($auction->isLive(), 404);
        $auction->load('lots.item.images', 'lots.leader:id,name');

        $current = $auction->lots->firstWhere('status', LotStatus::Live);

        return Inertia::render('Admin/Auctions/Live', [
            'auction' => [
                'id' => $auction->id, 'code' => $auction->code, 'slug' => $auction->slug, 'title' => $auction->title,
                'status' => Present::status($auction->status), 'stream_url' => $auction->stream_url,
                'stream_embed' => $auction->streamEmbedUrl(),
            ],
            'lots' => $auction->lots->map(fn (Lot $lot) => [
                'id' => $lot->id, 'lot_number' => $lot->lot_number, 'title' => $lot->item->title,
                'starting_price' => $lot->starting_price, 'current_price' => $lot->current_price,
                'bids_count' => $lot->bids_count, 'leader' => $lot->leader?->name,
                'status' => Present::status($lot->status),
            ]),
            'current' => $current ? [
                'id' => $current->id,
                'lot_number' => $current->lot_number,
                'title' => $current->item->title,
                'image' => $current->item->images->first()?->url(),
                'starting_price' => $current->starting_price,
                'reserve_price' => $current->reserve_price,
                'current_price' => $current->bids_count ? $current->current_price : $current->starting_price,
                'next_bid' => $increments->minimumNextBid($current),
                'bids_count' => $current->bids_count,
                'leader' => $current->leader?->name,
                'reserve_met' => $current->reserve_price <= 0 || $current->reserveMet(),
                'live_calls' => $current->live_calls,
                'bids' => Bid::with('user:id,name')->where('lot_id', $current->id)->latest('id')->limit(12)->get()
                    ->map(fn (Bid $b) => ['id' => $b->id, 'bidder' => $b->user->name, 'amount' => $b->amount, 'is_auto' => $b->is_auto, 'at' => $b->created_at->toIso8601String()]),
            ] : null,
        ]);
    }

    public function open(Auction $auction, Lot $lot, LotCloser $closer): RedirectResponse
    {
        return $this->run($auction, $lot, fn () => $closer->openLive($lot), "Lot {$lot->lot_number} dibuka. Silakan mulai penawaran.");
    }

    public function call(Auction $auction, Lot $lot, LotCloser $closer): RedirectResponse
    {
        return $this->run($auction, $lot, function () use ($closer, $lot) {
            $n = $closer->callLive($lot);
            session()->flash('info', $n === 1 ? 'Panggilan PERTAMA diumumkan.' : 'Panggilan KEDUA diumumkan.');
        });
    }

    public function hammer(Auction $auction, Lot $lot, LotCloser $closer): RedirectResponse
    {
        return $this->run($auction, $lot, function () use ($closer, $lot) {
            $closed = $closer->hammer($lot);
            session()->flash('success', $closed->status === LotStatus::Sold
                ? "TERJUAL! Lot {$closed->lot_number} seharga Rp ".number_format($closed->current_price, 0, ',', '.').'.'
                : "Lot {$closed->lot_number} tidak laku (harga limit tidak tercapai).");
        });
    }

    private function run(Auction $auction, Lot $lot, callable $action, ?string $success = null): RedirectResponse
    {
        abort_unless($auction->isLive() && $lot->auction_id === $auction->id, 404);

        try {
            $action();
        } catch (BidException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $success ? back()->with('success', $success) : back();
    }
}
