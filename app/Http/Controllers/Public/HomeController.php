<?php

namespace App\Http\Controllers\Public;

use App\Enums\AuctionStatus;
use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Support\Present;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $with = ['item.images', 'item.category'];

        return Inertia::render('Public/Home', [
            'live' => Lot::publiclyVisible()->with($with)->where('status', LotStatus::Live)
                ->orderBy('ends_at')->limit(8)->get()->map(fn ($l) => Present::lotCard($l)),
            'upcoming' => Lot::publiclyVisible()->with($with)->where('status', LotStatus::Scheduled)
                ->orderBy('starts_at')->limit(8)->get()->map(fn ($l) => Present::lotCard($l)),
            'auctions' => Auction::whereIn('status', [AuctionStatus::Published, AuctionStatus::Live])
                ->withCount('lots')->orderBy('starts_at')->limit(3)->get()
                ->map(fn ($a) => [
                    'slug' => $a->slug, 'title' => $a->title, 'starts_at' => $a->starts_at->toIso8601String(),
                    'ends_at' => $a->ends_at->toIso8601String(), 'lots_count' => $a->lots_count,
                    'status' => Present::status($a->status),
                    'method' => Present::status($a->method),
                ]),
            'categories' => Category::withCount('items')->orderBy('name')->get(['id', 'name', 'slug', 'icon']),
            'stats' => [
                'sold' => Lot::where('status', LotStatus::Sold)->count(),
                'live' => Lot::where('status', LotStatus::Live)->count(),
            ],
        ]);
    }
}
