<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\AuctionRegistration;
use App\Models\Bid;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Lot;
use App\Models\Settlement;
use App\Models\User;
use App\Support\Present;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $paid = Invoice::where('status', InvoiceStatus::Paid);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'live_lots' => Lot::where('status', LotStatus::Live)->count(),
                'bids_today' => Bid::whereDate('created_at', today())->count(),
                'items_waiting' => Item::whereIn('status', [ItemStatus::Received, ItemStatus::Inspected])->count(),
                'items_ready' => Item::where('status', ItemStatus::Approved)->count(),
                'kyc_pending' => User::where('kyc_status', KycStatus::Pending)->count(),
                'registrations_pending' => AuctionRegistration::where('status', RegistrationStatus::Pending)->count(),
                'invoices_unpaid' => Invoice::where('status', InvoiceStatus::Unpaid)->count(),
                'invoices_unpaid_total' => (int) Invoice::where('status', InvoiceStatus::Unpaid)->sum('total'),
                'settlements_pending' => Settlement::where('status', SettlementStatus::Pending)->count(),
                'settlements_pending_total' => (int) Settlement::where('status', SettlementStatus::Pending)->sum('net_amount'),
                'revenue_month' => (int) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
                'gmv_total' => (int) (clone $paid)->sum('hammer_price'),
            ],
            'endingSoon' => Lot::with('item.images', 'item.category')->where('status', LotStatus::Live)
                ->orderBy('ends_at')->limit(6)->get()->map(fn ($l) => Present::lotCard($l)),
            'recentBids' => Bid::with('user:id,name', 'lot.item:id,title')->latest('id')->limit(10)->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'user' => $b->user->name,
                    'lot_id' => $b->lot_id,
                    'title' => $b->lot->item->title,
                    'amount' => $b->amount,
                    'is_auto' => $b->is_auto,
                    'at' => $b->created_at->toIso8601String(),
                ]),
        ]);
    }
}
