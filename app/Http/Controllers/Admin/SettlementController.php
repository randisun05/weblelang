<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PaymentManager;
use App\Payments\PayoutService;
use App\Services\ImageService;
use App\Services\SettlementService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function index(Request $request, PaymentManager $manager): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(SettlementStatus::class)]]);

        return Inertia::render('Admin/Settlements/Index', [
            'payoutEnabled' => $manager->payoutsEnabled(),
            'settlements' => Settlement::with('consignor', 'invoice.lot.item:id,title', 'payouts')
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->orderByRaw("case status when 'pending' then 0 else 1 end")->latest()
                ->paginate(20)->withQueryString()
                ->through(fn (Settlement $s) => [
                    'id' => $s->id, 'number' => $s->number, 'title' => $s->invoice->lot->item->title,
                    'consignor' => [
                        'id' => $s->consignor->id, 'name' => $s->consignor->name, 'bank_name' => $s->consignor->bank_name,
                        'bank_account' => $s->consignor->bank_account, 'bank_holder' => $s->consignor->bank_holder,
                    ],
                    'hammer_price' => $s->hammer_price, 'commission_rate' => $s->commission_rate,
                    'commission' => $s->commission, 'net_amount' => $s->net_amount,
                    'status' => Present::status($s->status), 'paid_at' => $s->paid_at?->toIso8601String(),
                    'has_proof' => (bool) $s->transfer_proof,
                    'payout' => ($p = $s->payouts->first()) ? [
                        'reference' => $p->reference, 'gateway' => $p->gateway,
                        'status' => Present::status($p->status), 'failure_reason' => $p->failure_reason,
                    ] : null,
                ]),
            'filters' => $filters,
            'statuses' => SettlementStatus::options(),
        ]);
    }

    public function markPaid(Request $request, Settlement $settlement, SettlementService $service, ImageService $images, PayoutService $payouts): RedirectResponse
    {
        if ($settlement->status === SettlementStatus::Paid) {
            return back()->with('info', 'Settlement ini sudah ditandai dibayar.');
        }

        if ($payouts->inFlight($settlement)) {
            return back()->with('error', 'Transfer via gateway sedang diproses. Tunggu hasilnya sebelum menandai manual.');
        }

        $request->validate(['proof' => ['required', 'image', 'max:5120']]);

        $service->markPaid($settlement, $images->store($request->file('proof'), 'settlement-proofs', 'local', 1400));

        return back()->with('success', 'Settlement ditandai sudah ditransfer ke penitip.');
    }

    /** Transfer hasil lelang ke rekening penitip lewat disbursement gateway. */
    public function payout(Request $request, Settlement $settlement, PayoutService $payouts): RedirectResponse
    {
        try {
            $payout = $payouts->send($settlement, $request->user());
        } catch (GatewayException $e) {
            return back()->with('error', 'Transfer gagal: '.$e->getMessage());
        }

        return back()->with('success', $payout->status->value === 'completed'
            ? 'Transfer ke penitip berhasil.'
            : 'Transfer dikirim ke gateway ('.$payout->reference.'). Status diperbarui otomatis.');
    }

    public function proof(Settlement $settlement): StreamedResponse
    {
        abort_unless($settlement->transfer_proof && Storage::disk('local')->exists($settlement->transfer_proof), 404);

        return Storage::disk('local')->response($settlement->transfer_proof, headers: ['Cache-Control' => 'private, no-store']);
    }
}
