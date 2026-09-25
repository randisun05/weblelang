<?php

namespace App\Http\Controllers\User;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Payments\PaymentManager;
use App\Services\DocumentService;
use App\Services\ImageService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('User/Invoices/Index', [
            'invoices' => $request->user()->invoices()->with('lot.item')->latest()->paginate(15)
                ->through(fn (Invoice $inv) => [
                    'id' => $inv->id,
                    'number' => $inv->number,
                    'title' => $inv->lot->item->title,
                    'total' => $inv->total,
                    'due_at' => $inv->due_at->toIso8601String(),
                    'status' => Present::status($inv->status),
                ]),
        ]);
    }

    public function show(Request $request, Invoice $invoice, PaymentManager $payments): Response
    {
        $this->authorizeOwner($request, $invoice);
        $invoice->load('lot.item.images', 'lot.auction');

        return Inertia::render('User/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'title' => $invoice->lot->item->title,
                'image' => $invoice->lot->item->images->first()?->url(),
                'lot_id' => $invoice->lot_id,
                'auction' => $invoice->lot->auction->title,
                'hammer_price' => $invoice->hammer_price,
                'buyer_premium' => $invoice->buyer_premium,
                'buyer_premium_rate' => $invoice->lot->auction->buyer_premium_rate,
                'admin_fee' => $invoice->admin_fee,
                'total' => $invoice->total,
                'due_at' => $invoice->due_at->toIso8601String(),
                'paid_at' => $invoice->paid_at?->toIso8601String(),
                'delivered_at' => $invoice->delivered_at?->toIso8601String(),
                'payment_method' => $invoice->payment_method,
                'has_proof' => (bool) $invoice->payment_proof,
                'cancel_reason' => $invoice->cancel_reason,
                'status' => Present::status($invoice->status),
            ],
            'onlinePayment' => $payments->paymentsEnabled(),
            'bank' => config('auction.bank'),
        ]);
    }

    public function uploadProof(Request $request, Invoice $invoice, ImageService $images): RedirectResponse
    {
        $this->authorizeOwner($request, $invoice);

        if ($invoice->status !== InvoiceStatus::Unpaid) {
            return back()->with('error', 'Invoice ini tidak lagi menunggu pembayaran.');
        }

        $request->validate(['proof' => ['required', 'image', 'max:5120']]);

        $invoice->forceFill([
            'payment_proof' => $images->store($request->file('proof'), 'payment-proofs', 'local', 1400),
            'payment_method' => 'transfer',
        ])->save();

        return back()->with('success', 'Bukti transfer terkirim. Admin akan mengonfirmasi pembayaran Anda.');
    }

    public function pdf(Request $request, Invoice $invoice, DocumentService $documents): HttpResponse
    {
        $this->authorizeOwner($request, $invoice);

        return $documents->invoice($invoice);
    }

    private function authorizeOwner(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);
    }
}
