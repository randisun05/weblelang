<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
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
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(SettlementStatus::class)]]);

        return Inertia::render('Admin/Settlements/Index', [
            'settlements' => Settlement::with('consignor', 'invoice.lot.item:id,title')
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
                ]),
            'filters' => $filters,
            'statuses' => SettlementStatus::options(),
        ]);
    }

    public function markPaid(Request $request, Settlement $settlement, SettlementService $service, ImageService $images): RedirectResponse
    {
        if ($settlement->status === SettlementStatus::Paid) {
            return back()->with('info', 'Settlement ini sudah ditandai dibayar.');
        }

        $request->validate(['proof' => ['required', 'image', 'max:5120']]);

        $service->markPaid($settlement, $images->store($request->file('proof'), 'settlement-proofs', 'local', 1400));

        return back()->with('success', 'Settlement ditandai sudah ditransfer ke penitip.');
    }

    public function proof(Settlement $settlement): StreamedResponse
    {
        abort_unless($settlement->transfer_proof && Storage::disk('local')->exists($settlement->transfer_proof), 404);

        return Storage::disk('local')->response($settlement->transfer_proof, headers: ['Cache-Control' => 'private, no-store']);
    }
}
