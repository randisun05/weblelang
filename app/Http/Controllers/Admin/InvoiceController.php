<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => Invoice::with('user:id,name', 'lot.item:id,title')
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('number', 'like', "%{$s}%"))
                ->latest()->paginate(20)->withQueryString()
                ->through(fn (Invoice $i) => [
                    'id' => $i->id, 'number' => $i->number, 'user' => $i->user->name, 'title' => $i->lot->item->title,
                    'total' => $i->total, 'due_at' => $i->due_at->toIso8601String(), 'overdue' => $i->status === InvoiceStatus::Unpaid && $i->due_at->isPast(),
                    'has_proof' => (bool) $i->payment_proof, 'status' => Present::status($i->status),
                    'delivered' => (bool) $i->delivered_at,
                ]),
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load('user', 'lot.item.consignor', 'lot.auction', 'settlement');

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id, 'number' => $invoice->number,
                'user' => ['id' => $invoice->user->id, 'name' => $invoice->user->name, 'email' => $invoice->user->email, 'phone' => $invoice->user->phone],
                'item' => ['id' => $invoice->lot->item->id, 'code' => $invoice->lot->item->code, 'title' => $invoice->lot->item->title],
                'consignor' => $invoice->lot->item->consignor->name,
                'auction' => $invoice->lot->auction->title,
                'lot_number' => $invoice->lot->lot_number,
                'hammer_price' => $invoice->hammer_price, 'buyer_premium' => $invoice->buyer_premium,
                'admin_fee' => $invoice->admin_fee, 'total' => $invoice->total,
                'due_at' => $invoice->due_at->toIso8601String(), 'paid_at' => $invoice->paid_at?->toIso8601String(),
                'delivered_at' => $invoice->delivered_at?->toIso8601String(),
                'payment_method' => $invoice->payment_method, 'payment_ref' => $invoice->payment_ref,
                'has_proof' => (bool) $invoice->payment_proof,
                'status' => Present::status($invoice->status),
                'settlement' => $invoice->settlement ? [
                    'number' => $invoice->settlement->number, 'net_amount' => $invoice->settlement->net_amount,
                    'status' => Present::status($invoice->settlement->status),
                ] : null,
            ],
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);

        try {
            $invoices->markPaid($invoice, $invoice->payment_method ?: 'transfer', $data['reference'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran dikonfirmasi. Settlement penitip dibuat otomatis.');
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $invoices->cancel($invoice, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice dibatalkan. Barang kembali siap dilelang ulang.');
    }

    public function deliver(Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoices->markDelivered($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Barang tercatat sudah diserahkan ke pemenang.');
    }

    public function proof(Invoice $invoice): StreamedResponse
    {
        abort_unless($invoice->payment_proof && Storage::disk('local')->exists($invoice->payment_proof), 404);

        return Storage::disk('local')->response($invoice->payment_proof, headers: ['Cache-Control' => 'private, no-store']);
    }
}
