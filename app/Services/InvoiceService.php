<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Models\Invoice;
use App\Models\Lot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(private SettlementService $settlements) {}

    public function createForLot(Lot $lot): Invoice
    {
        $hammer = $lot->current_price;
        $premium = (int) round($hammer * $lot->auction->buyer_premium_rate / 100);
        $adminFee = (int) config('auction.admin_fee', 0);

        return Invoice::create([
            'user_id' => $lot->leader_id,
            'lot_id' => $lot->id,
            'hammer_price' => $hammer,
            'buyer_premium' => $premium,
            'admin_fee' => $adminFee,
            'total' => $hammer + $premium + $adminFee,
            'due_at' => now()->addHours((int) config('auction.invoice_due_hours', 72)),
        ]);
    }

    /**
     * Menandai invoice lunas (idempoten) lalu membuat settlement untuk penitip.
     */
    public function markPaid(Invoice $invoice, string $method, ?string $reference = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $method, $reference) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === InvoiceStatus::Paid) {
                return $invoice;
            }

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new RuntimeException('Invoice ini sudah dibatalkan.');
            }

            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'payment_method' => $method,
                'payment_ref' => $reference,
            ])->save();

            $this->settlements->createForInvoice($invoice);

            AuditLogger::log('invoice.paid', $invoice, ['method' => $method, 'ref' => $reference]);

            return $invoice;
        });
    }

    /** Pembatalan karena wanprestasi: barang kembali siap dilelang ulang. */
    public function cancel(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new RuntimeException('Hanya invoice yang belum dibayar yang dapat dibatalkan.');
            }

            $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();
            $invoice->lot->item->forceFill(['status' => ItemStatus::Approved])->save();

            AuditLogger::log('invoice.cancelled', $invoice, ['reason' => $reason]);
        });
    }

    public function markDelivered(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Paid) {
            throw new RuntimeException('Barang hanya dapat diserahkan setelah invoice lunas.');
        }

        $invoice->forceFill(['delivered_at' => now()])->save();
        $invoice->lot->item->forceFill(['status' => ItemStatus::Delivered])->save();

        AuditLogger::log('invoice.delivered', $invoice);
    }
}
