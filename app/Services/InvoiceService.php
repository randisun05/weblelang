<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Models\AuctionRegistration;
use App\Models\Invoice;
use App\Models\Lot;
use App\Notifications\InvoiceStatusNotification;
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
            $invoice->user->notify(new InvoiceStatusNotification($invoice));

            return $invoice;
        });
    }

    /**
     * Pembatalan karena wanprestasi: barang kembali siap dilelang ulang dan
     * uang jaminan pemenang pada sesi tersebut (jika ada) dinyatakan hangus.
     */
    public function cancel(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new RuntimeException('Hanya invoice yang belum dibayar yang dapat dibatalkan.');
            }

            $invoice->forceFill(['status' => InvoiceStatus::Cancelled, 'cancel_reason' => $reason])->save();
            $invoice->lot->item->forceFill(['status' => ItemStatus::Approved])->save();

            $forfeited = AuctionRegistration::where('auction_id', $invoice->lot->auction_id)
                ->where('user_id', $invoice->user_id)
                ->where('deposit_status', DepositStatus::Held)
                ->update(['deposit_status' => DepositStatus::Forfeited, 'deposit_settled_at' => now()]);

            AuditLogger::log('invoice.cancelled', $invoice, ['reason' => $reason, 'deposit_forfeited' => (bool) $forfeited]);
            $invoice->user->notify(new InvoiceStatusNotification($invoice));
        });
    }

    /** Membatalkan otomatis invoice yang lewat jatuh tempo (dijalankan scheduler). */
    public function cancelOverdue(): int
    {
        if (! config('auction.auto_cancel_overdue', true)) {
            return 0;
        }

        $count = 0;
        Invoice::where('status', InvoiceStatus::Unpaid)
            ->where('due_at', '<', now())
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                try {
                    $this->cancel(Invoice::findOrFail($id), 'Tidak dibayar sebelum jatuh tempo (otomatis)');
                    $count++;
                } catch (RuntimeException) {
                    // Sudah dibayar/dibatalkan oleh proses lain di antara query dan lock.
                }
            });

        return $count;
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
