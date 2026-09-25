<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Log transaksi gateway: uang masuk (payments) & uang keluar (payouts). */
class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'payouts' ? 'payouts' : 'payments';

        $rows = $tab === 'payments'
            ? Payment::with('user:id,name', 'payable')->latest('id')->paginate(30)->withQueryString()
                ->through(fn (Payment $p) => [
                    'reference' => $p->reference, 'gateway' => $p->gateway, 'amount' => $p->amount,
                    'party' => $p->user->name, 'subject' => $p->description(), 'method' => $p->method,
                    'provider_ref' => $p->provider_ref, 'status' => Present::status($p->status),
                    'at' => ($p->paid_at ?? $p->created_at)->toIso8601String(),
                ])
            : Payout::with('payable', 'requester:id,name')->latest('id')->paginate(30)->withQueryString()
                ->through(fn (Payout $p) => [
                    'reference' => $p->reference, 'gateway' => $p->gateway, 'amount' => $p->amount,
                    'party' => "{$p->account_holder} · {$p->bank_code} {$p->maskedAccount()}",
                    'subject' => class_basename($p->payable_type) === 'Settlement' ? 'Settlement '.$p->payable?->number : 'Refund jaminan',
                    'method' => $p->requester?->name ?? 'Otomatis', 'provider_ref' => $p->provider_ref,
                    'failure_reason' => $p->failure_reason, 'status' => Present::status($p->status),
                    'at' => ($p->completed_at ?? $p->created_at)->toIso8601String(),
                ]);

        return Inertia::render('Admin/Transactions/Index', [
            'tab' => $tab,
            'rows' => $rows,
            'gateways' => ['payment' => config('payments.gateway'), 'payout' => config('payments.payout_gateway')],
        ]);
    }
}
