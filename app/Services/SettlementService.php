<?php

namespace App\Services;

use App\Enums\SettlementStatus;
use App\Models\Invoice;
use App\Models\Settlement;
use App\Notifications\ConsignorSettlementNotification;

/** Menghitung dan mencatat penyetoran hasil lelang ke penitip. */
class SettlementService
{
    public function createForInvoice(Invoice $invoice): Settlement
    {
        $item = $invoice->lot->item;
        $rate = $item->effectiveCommissionRate();
        $commission = (int) round($invoice->hammer_price * $rate / 100);

        $settlement = Settlement::firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'consignor_id' => $item->consignor_id,
                'hammer_price' => $invoice->hammer_price,
                'commission_rate' => $rate,
                'commission' => $commission,
                'other_fees' => 0,
                'net_amount' => max(0, $invoice->hammer_price - $commission),
            ],
        );

        if ($settlement->wasRecentlyCreated) {
            $settlement->consignor?->notify(new ConsignorSettlementNotification($settlement, ConsignorSettlementNotification::SOLD));
        }

        return $settlement;
    }

    public function markPaid(Settlement $settlement, ?string $proofPath): void
    {
        $settlement->forceFill([
            'status' => SettlementStatus::Paid,
            'paid_at' => now(),
            'transfer_proof' => $proofPath,
        ])->save();

        AuditLogger::log('settlement.paid', $settlement, ['net' => $settlement->net_amount]);
        $settlement->consignor?->notify(new ConsignorSettlementNotification($settlement, ConsignorSettlementNotification::PAID));
    }
}
