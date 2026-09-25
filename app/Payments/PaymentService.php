<?php

namespace App\Payments;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\AuctionRegistration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Services\AuditLogger;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Alur uang masuk yang generik: apa pun yang "bisa dibayar" (invoice, jaminan, ...)
 * melewati checkout() → webhook → process() → fulfil().
 */
class PaymentService
{
    public function __construct(private PaymentManager $manager, private InvoiceService $invoices) {}

    /** Membuat (atau memakai ulang) sesi pembayaran untuk sebuah tagihan. */
    public function checkout(Model $payable, User $user): Payment
    {
        $amount = $this->amountDue($payable, $user);
        $gateway = $this->manager->paymentGateway();

        $existing = Payment::whereMorphedTo('payable', $payable)
            ->where('gateway', $gateway->name())
            ->where('amount', $amount)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')->first();

        if ($existing?->isReusable()) {
            return $existing;
        }

        $payment = new Payment([
            'gateway' => $gateway->name(),
            'amount' => $amount,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes((int) config('payments.checkout_expiry_minutes', 1440)),
        ]);
        $payment->payable()->associate($payable);
        $payment->save();

        try {
            $result = $gateway->createCheckout($payment);
        } catch (GatewayException $e) {
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();
            throw $e;
        }
        $payment->forceFill(['checkout_url' => $result->url, 'provider_ref' => $result->providerRef])->save();

        AuditLogger::log('payment.checkout', $payment, ['gateway' => $gateway->name(), 'amount' => $amount]);

        return $payment;
    }

    /** Memproses webhook sebuah gateway. @throws InvalidWebhook */
    public function handleWebhook(string $gateway, Request $request): ?Payment
    {
        return $this->process($gateway, $this->manager->driver($gateway)->parsePaymentWebhook($request));
    }

    /**
     * Menerapkan event gateway secara idempoten (webhook bisa datang berkali-kali / tidak berurutan).
     *
     * @throws InvalidWebhook
     */
    public function process(string $gateway, GatewayEvent $event): ?Payment
    {
        return DB::transaction(function () use ($gateway, $event) {
            $payment = Payment::where('reference', $event->reference)->lockForUpdate()->first();

            if (! $payment) {
                Log::warning('Payment webhook untuk referensi tidak dikenal', ['gateway' => $gateway, 'reference' => $event->reference]);

                return null;
            }

            if ($payment->gateway !== $gateway) {
                throw new InvalidWebhook('Gateway tidak cocok dengan transaksi.');
            }

            if ($payment->status === PaymentStatus::Paid) {
                return $payment; // sudah diproses
            }

            if ($event->status === 'paid') {
                if ($event->amount !== $payment->amount) {
                    Log::warning('Nominal pembayaran tidak cocok', ['reference' => $payment->reference, 'expected' => $payment->amount, 'got' => $event->amount]);
                    throw new InvalidWebhook('Nominal pembayaran tidak cocok.');
                }

                $payment->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'method' => $event->method,
                    'provider_ref' => $event->providerRef ?? $payment->provider_ref,
                    'payload' => $event->raw,
                ])->save();

                AuditLogger::log('payment.paid', $payment, ['gateway' => $gateway, 'method' => $event->method]);
                $this->fulfil($payment);
            } elseif (in_array($event->status, ['failed', 'expired'], true)) {
                $payment->forceFill(['status' => PaymentStatus::from($event->status), 'payload' => $event->raw])->save();
            }

            return $payment;
        });
    }

    /** Nominal yang harus dibayar + validasi bahwa tagihan memang milik & masih terbuka untuk user ini. */
    private function amountDue(Model $payable, User $user): int
    {
        return match (true) {
            $payable instanceof Invoice => $payable->user_id === $user->id && $payable->status === InvoiceStatus::Unpaid
                ? $payable->total
                : throw new GatewayException('Invoice ini tidak dapat dibayar.'),
            $payable instanceof AuctionRegistration => $payable->user_id === $user->id
                && $payable->status !== RegistrationStatus::Approved
                && $payable->auction->deposit_amount > 0
                ? $payable->auction->deposit_amount
                : throw new GatewayException('Jaminan untuk sesi ini tidak perlu dibayar.'),
            default => throw new GatewayException('Jenis tagihan tidak didukung.'),
        };
    }

    /** Efek bisnis setelah uang benar-benar masuk. */
    private function fulfil(Payment $payment): void
    {
        $payable = $payment->payable;
        $method = trim("gateway:{$payment->gateway}:".($payment->method ?? ''), ':');

        if ($payable instanceof Invoice) {
            try {
                $this->invoices->markPaid($payable, $method, $payment->reference);
            } catch (RuntimeException $e) {
                // Uang sudah diterima tapi invoice sudah dibatalkan → perlu refund manual oleh admin.
                AuditLogger::log('payment.needs_refund', $payment, ['reason' => $e->getMessage()]);
                Log::error('Pembayaran diterima untuk invoice non-aktif', ['payment' => $payment->reference]);
            }

            return;
        }

        if ($payable instanceof AuctionRegistration) {
            $payable->forceFill([
                'status' => RegistrationStatus::Approved,
                'deposit_status' => DepositStatus::Held,
                'note' => "Jaminan dibayar via {$payment->gateway} ({$payment->reference})",
            ])->save();
            AuditLogger::log('registration.approved', $payable, ['via' => 'payment', 'payment' => $payment->reference]);
        }
    }
}
