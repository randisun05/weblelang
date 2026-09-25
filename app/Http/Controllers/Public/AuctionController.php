<?php

namespace App\Http\Controllers\Public;

use App\Enums\AuctionStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuctionController extends Controller
{
    public function index(): Response
    {
        $auctions = Auction::where('status', '!=', AuctionStatus::Draft)
            ->withCount('lots')
            ->orderByRaw("case status when 'live' then 0 when 'published' then 1 else 2 end")
            ->orderByDesc('starts_at')
            ->paginate(12)
            ->through(fn (Auction $a) => [
                'slug' => $a->slug,
                'code' => $a->code,
                'title' => $a->title,
                'description' => $a->description,
                'starts_at' => $a->starts_at->toIso8601String(),
                'ends_at' => $a->ends_at->toIso8601String(),
                'lots_count' => $a->lots_count,
                'deposit_amount' => $a->deposit_amount,
                'status' => Present::status($a->status),
            ]);

        return Inertia::render('Public/Auctions/Index', ['auctions' => $auctions]);
    }

    public function show(Request $request, Auction $auction): Response
    {
        abort_unless($auction->isPublic(), 404);

        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:lot,ending,price_low,price_high'],
        ]);

        $lots = $auction->lots()->getQuery()
            ->with(['item.images', 'item.category'])
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->whereHas('item', fn ($i) => $i->where('category_id', $c)))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->whereHas('item', fn ($i) => $i->where('title', 'like', "%{$s}%")))
            ->reorder()
            ->when(($filters['sort'] ?? 'lot') === 'lot', fn ($q) => $q->orderBy('lot_number'))
            ->when(($filters['sort'] ?? null) === 'ending', fn ($q) => $q->orderBy('ends_at'))
            ->when(($filters['sort'] ?? null) === 'price_low', fn ($q) => $q->orderByRaw('case when bids_count > 0 then current_price else starting_price end asc'))
            ->when(($filters['sort'] ?? null) === 'price_high', fn ($q) => $q->orderByRaw('case when bids_count > 0 then current_price else starting_price end desc'))
            ->paginate(24)->withQueryString()
            ->through(fn ($lot) => Present::lotCard($lot));

        $registration = $request->user()
            ? $auction->registrations()->where('user_id', $request->user()->id)->first()
            : null;

        return Inertia::render('Public/Auctions/Show', [
            'auction' => [
                'id' => $auction->id,
                'slug' => $auction->slug,
                'code' => $auction->code,
                'title' => $auction->title,
                'description' => $auction->description,
                'starts_at' => $auction->starts_at->toIso8601String(),
                'ends_at' => $auction->ends_at->toIso8601String(),
                'deposit_amount' => $auction->deposit_amount,
                'buyer_premium_rate' => $auction->buyer_premium_rate,
                'anti_snipe_minutes' => $auction->anti_snipe_minutes,
                'extend_minutes' => $auction->extend_minutes,
                'status' => Present::status($auction->status),
            ],
            'registration' => $registration ? Present::status($registration->status) : null,
            'deposit' => $registration?->deposit_status ? Present::status($registration->deposit_status) : null,
            'lots' => $lots,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
