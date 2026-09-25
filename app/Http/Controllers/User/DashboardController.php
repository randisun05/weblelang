<?php

namespace App\Http\Controllers\User;

use App\Enums\InvoiceStatus;
use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isBackoffice()) {
            return redirect()->route('admin.dashboard');
        }

        $with = ['item.images', 'item.category'];

        $active = Lot::with($with)
            ->whereIn('status', [LotStatus::Live, LotStatus::Scheduled])
            ->whereHas('bids', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('ends_at')->get()
            ->map(fn ($lot) => Present::lotCard($lot) + ['is_leader' => $lot->isConcealed() ? null : $lot->leader_id === $user->id]);

        $won = Lot::with($with)->where('status', LotStatus::Sold)->where('leader_id', $user->id)
            ->latest('closed_at')->limit(12)->get()->map(fn ($lot) => Present::lotCard($lot));

        return Inertia::render('User/Dashboard', [
            'active' => $active,
            'won' => $won,
            'watchlist' => $user->watchlist()->with($with)->latest('watchlists.created_at')->limit(12)->get()
                ->map(fn ($lot) => Present::lotCard($lot)),
            'unpaidInvoices' => $user->invoices()->where('status', InvoiceStatus::Unpaid)->count(),
            'kyc' => Present::status($user->kyc_status),
        ]);
    }
}
