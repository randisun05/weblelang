<?php

namespace App\Payments;

use App\Enums\AuctionStatus;
use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PayoutStatus;
use App\Enums\SettlementStatus;
use App\Models\AuctionRegistration;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\DepositSettledNotification;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Services\AuditLogger;
use App\Services\SettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alur uang keluar yang generik: settlement penitip dan refund jaminan peserta.
 * Satu tagihan hanya boleh punya satu payout aktif → tidak ada transfer ganda.
 */
class PayoutService
{
    public function __construct(private PaymentManager $manager, private SettlementService $settlements) {}

    /** Ada transfer gateway yang masih berjalan (belum sukses/gagal) untuk tagihan ini? */
    public function inFlight(Model $payable): bool
    {
        return $payable->payouts()->whereIn('status', [PayoutStatus::Pending, PayoutStatus::Processing])->exists();
    }

    /** @throws GatewayException */
    public function send(Model $payable, ?User $actor = null): Payout
    {
        $gateway = $this->manager->payoutGateway();

        $payout = DB::transaction(function () use ($payable, $actor, $gateway) {
            // Kunci tagihan supaya dua klik / dua proses tidak membuat dua payout.
            $payable = $payable->newQuery()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();
            [$amount, $bank, $holder, $account] = $this->destination($payable);

            if ($payable->payouts()->get()->contains(fn (Payout $p) => $p->status->isActive())) {
                throw new GatewayException('Sudah ada transfer yang sedang/telah diproses untuk data ini.');
            }

            $payout = new Payout([
                'gateway' => $gateway->name(),
                'amount' => $amount,
                'bank_code' => $bank,
                'account_number' => $account,
                'account_holder' => $holder,
                'requested_by' => $actor?->id,
            ]);
            $payout->payable()->associate($payable);
            $payout->save();

            return $payout;
        });

        try {
            $event = $gateway->createPayout($payout);
        } catch (GatewayException $e) {
            $payout->forceFill(['status' => PayoutStatus::Failed, 'failure_reason' => $e->getMessage()])->save();
            AuditLogger::log('payout.failed', $payout, ['reason' => $e->getMessage()]);
            throw $e;
        }

        AuditLogger::log('payout.created', $payout, ['gateway' => $gateway->name(), 'amount' => $payout->amount]);

        return $this->apply($payout, $event);
    }

    /** @throws InvalidWebhook */
    public function handleWebhook(string $gateway, Request $request): ?Payout
    {
        $event = $this->manager->driver($gateway)->parsePayoutWebhook($request);

        $payout = Payout::where('gateway', $gateway)
            ->where(fn ($q) => $q->where('reference', $event->reference)->orWhere('provider_ref', $event->providerRef ?? $event->reference))
            ->first();

        if (! $payout) {
            Log::warning('Payout webhook untuk referensi tidak dikenal', ['gateway' => $gateway, 'reference' => $event->reference]);

            return null;
        }

        if ($event->amount !== null && $event->amount !== $payout->amount) {
            throw new InvalidWebhook('Nominal payout tidak cocok.');
        }

        return $this->apply($payout, $event);
    }

    /** Menerapkan status baru (idempoten; status akhir tidak bisa berubah lagi). */
    public function apply(Payout $payout, GatewayEvent $event): Payout
    {
        return DB::transaction(function () use ($payout, $event) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (in_array($payout->status, [PayoutStatus::Completed, PayoutStatus::Failed], true)) {
                return $payout;
            }

            $payout->forceFill([
                'status' => match ($event->status) {
                    'completed' => PayoutStatus::Completed,
                    'failed' => PayoutStatus::Failed,
                    default => PayoutStatus::Processing,
                },
                'provider_ref' => $event->providerRef ?? $payout->provider_ref,
                'failure_reason' => $event->failureReason,
                'payload' => $event->raw ?: $payout->payload,
            ])->save();

            if ($payout->status === PayoutStatus::Completed) {
                $payout->forceFill(['completed_at' => now()])->save();
                $this->fulfil($payout);
                AuditLogger::log('payout.completed', $payout);
            } elseif ($payout->status === PayoutStatus::Failed) {
                AuditLogger::log('payout.failed', $payout, ['reason' => $event->failureReason]);
            }

            return $payout;
        });
    }

    /**
     * Refund otomatis uang jaminan (scheduler): sesi sudah selesai, jaminan masih ditahan,
     * peserta tidak punya invoice yang belum lunas di sesi itu, rekening sudah diisi,
     * dan belum pernah ada payout (payout gagal ditangani manual oleh admin).
     */
    public function refundDueDeposits(): int
    {
        if (! config('payments.auto_refund_deposits') || ! $this->manager->payoutsEnabled()) {
            return 0;
        }

        $sent = 0;
        AuctionRegistration::with('auction', 'user')
            ->where('deposit_status', DepositStatus::Held)
            ->whereHas('auction', fn ($q) => $q->where('status', AuctionStatus::Closed))
            ->whereDoesntHave('payouts')
            ->get()
            ->each(function (AuctionRegistration $registration) use (&$sent) {
                $hasUnpaid = Invoice::where('user_id', $registration->user_id)
                    ->where('status', InvoiceStatus::Unpaid)
                    ->whereHas('lot', fn ($q) => $q->where('auction_id', $registration->auction_id))
                    ->exists();

                if ($hasUnpaid || ! $registration->user->hasBankAccount()) {
                    return;
                }

                try {
                    $this->send($registration);
                    $sent++;
                } catch (GatewayException $e) {
                    Log::warning('Refund jaminan otomatis gagal', ['registration' => $registration->id, 'error' => $e->getMessage()]);
                }
            });

        return $sent;
    }

    /** @return array{0: int, 1: string, 2: string, 3: string} nominal, kode bank, nama pemilik, no. rekening */
    private function destination(Model $payable): array
    {
        [$amount, $owner] = match (true) {
            $payable instanceof Settlement => $payable->status === SettlementStatus::Pending
                ? [$payable->net_amount, $payable->consignor]
                : throw new GatewayException('Settlement ini sudah dibayar.'),
            $payable instanceof AuctionRegistration => $payable->deposit_status === DepositStatus::Held
                ? [$payable->auction->deposit_amount, $payable->user]
                : throw new GatewayException('Jaminan ini tidak dalam status ditahan.'),
            default => throw new GatewayException('Jenis payout tidak didukung.'),
        };

        if ($amount <= 0) {
            throw new GatewayException('Nominal transfer tidak valid.');
        }

        if (! array_key_exists((string) $owner->bank_name, config('payments.banks')) || blank($owner->bank_account) || blank($owner->bank_holder)) {
            throw new GatewayException('Data rekening tujuan belum lengkap atau kode bank tidak didukung.');
        }

        return [$amount, $owner->bank_name, $owner->bank_holder, $owner->bank_account];
    }

    private function fulfil(Payout $payout): void
    {
        $payable = $payout->payable;

        if ($payable instanceof Settlement) {
            if ($payable->status !== SettlementStatus::Paid) {
                $this->settlements->markPaid($payable, null);
            }

            return;
        }

        if ($payable instanceof AuctionRegistration && $payable->deposit_status === DepositStatus::Held) {
            $payable->forceFill(['deposit_status' => DepositStatus::Refunded, 'deposit_settled_at' => now()])->save();
            $payable->user->notify(new DepositSettledNotification($payable->load('auction')));
        }
    }
}
