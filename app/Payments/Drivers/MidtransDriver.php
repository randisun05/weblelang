<?php

namespace App\Payments\Drivers;

use App\Models\Payment;
use App\Models\Payout;
use App\Payments\CheckoutResult;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\PayoutGateway;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\GatewayEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Midtrans: Snap (pembayaran) + Iris (disbursement).
 * Docs: https://docs.midtrans.com/reference/snap-api  &  https://docs.midtrans.com/reference/iris-api
 */
class MidtransDriver implements PaymentGateway, PayoutGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    private function config(string $key): mixed
    {
        return config("payments.drivers.midtrans.{$key}");
    }

    private function baseUrl(): string
    {
        return $this->config('is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    public function createCheckout(Payment $payment): CheckoutResult
    {
        $serverKey = $this->config('server_key') ?: throw new GatewayException('MIDTRANS_SERVER_KEY belum diatur.');
        $user = $payment->user;

        try {
            $response = Http::withBasicAuth($serverKey, '')->acceptJson()->timeout(20)
                ->post($this->baseUrl().'/snap/v1/transactions', [
                    'transaction_details' => ['order_id' => $payment->reference, 'gross_amount' => $payment->amount],
                    'item_details' => [[
                        'id' => Str::limit($payment->reference, 50, ''),
                        'price' => $payment->amount,
                        'quantity' => 1,
                        'name' => Str::limit($payment->description(), 50, ''),
                    ]],
                    'customer_details' => ['first_name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
                    'expiry' => ['unit' => 'minutes', 'duration' => (int) max(1, now()->diffInMinutes($payment->expires_at))],
                    'callbacks' => ['finish' => $payment->returnUrl()],
                ]);
        } catch (ConnectionException) {
            throw new GatewayException('Tidak dapat terhubung ke Midtrans.');
        }

        if ($response->failed() || ! $response->json('redirect_url')) {
            throw new GatewayException('Midtrans menolak pembuatan transaksi.');
        }

        return new CheckoutResult($response->json('redirect_url'), $response->json('token'));
    }

    public function parsePaymentWebhook(Request $request): GatewayEvent
    {
        $p = $request->all();
        $serverKey = (string) $this->config('server_key');

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $field) {
            if (! isset($p[$field])) {
                throw new InvalidWebhook("Field {$field} tidak ada.");
            }
        }

        $expected = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$serverKey);
        if ($serverKey === '' || ! hash_equals($expected, (string) $p['signature_key'])) {
            throw new InvalidWebhook('Signature Midtrans tidak valid.');
        }

        $status = match ($p['transaction_status']) {
            'settlement' => 'paid',
            'capture' => ($p['fraud_status'] ?? 'accept') === 'accept' ? 'paid' : 'pending',
            'expire' => 'expired',
            'deny', 'cancel', 'failure' => 'failed',
            default => 'pending',
        };

        return new GatewayEvent(
            reference: $p['order_id'],
            status: $status,
            amount: (int) round((float) $p['gross_amount']),
            providerRef: $p['transaction_id'] ?? null,
            method: $p['payment_type'] ?? null,
            raw: $p,
        );
    }

    public function createPayout(Payout $payout): GatewayEvent
    {
        $creator = $this->config('iris_creator_key') ?: throw new GatewayException('MIDTRANS_IRIS_CREATOR_KEY belum diatur.');
        $base = $this->baseUrl().'/iris/api/v1';

        try {
            $response = Http::withBasicAuth($creator, '')->acceptJson()->timeout(20)
                ->withHeaders(['X-Idempotency-Key' => $payout->reference])
                ->post($base.'/payouts', ['payouts' => [[
                    'beneficiary_name' => $payout->account_holder,
                    'beneficiary_account' => $payout->account_number,
                    'beneficiary_bank' => strtolower($payout->bank_code),
                    'amount' => number_format($payout->amount, 1, '.', ''),
                    'notes' => Str::limit($payout->reference, 20, ''),
                ]]]);
        } catch (ConnectionException) {
            throw new GatewayException('Tidak dapat terhubung ke Midtrans Iris.');
        }

        $referenceNo = $response->json('payouts.0.reference_no');
        if ($response->failed() || ! $referenceNo) {
            throw new GatewayException('Midtrans Iris menolak payout: '.($response->json('error_message') ?? 'unknown error'));
        }

        // Jika approver key diisi, payout langsung disetujui. Bila persetujuan gagal, payout sudah
        // terbuat di Iris — JANGAN ditandai gagal (bisa terkirim ganda saat diulang); cukup
        // setujui manual di dashboard Iris, status akan diperbarui lewat webhook.
        $note = null;
        if ($approver = $this->config('iris_approver_key')) {
            try {
                $approved = Http::withBasicAuth($approver, '')->acceptJson()->timeout(20)
                    ->post($base.'/payouts/approve', ['reference_nos' => [$referenceNo]])->successful();
            } catch (ConnectionException) {
                $approved = false;
            }
            $note = $approved ? null : 'Menunggu persetujuan manual di dashboard Iris.';
        }

        return new GatewayEvent(reference: $payout->reference, status: 'processing', providerRef: $referenceNo, failureReason: $note, raw: $response->json() ?? []);
    }

    public function parsePayoutWebhook(Request $request): GatewayEvent
    {
        $merchantKey = (string) $this->config('iris_merchant_key');
        $expected = hash('sha512', $request->getContent().$merchantKey);

        if ($merchantKey === '' || ! hash_equals($expected, (string) $request->header('Iris-Signature'))) {
            throw new InvalidWebhook('Signature Iris tidak valid.');
        }

        $p = $request->all();
        if (! isset($p['reference_no'], $p['status'])) {
            throw new InvalidWebhook('Payload Iris tidak lengkap.');
        }

        return new GatewayEvent(
            // Iris mengirim reference_no miliknya; dicocokkan lewat provider_ref.
            reference: $p['reference_no'],
            status: match ($p['status']) {
                'completed' => 'completed',
                'failed', 'rejected' => 'failed',
                default => 'processing',
            },
            amount: isset($p['amount']) ? (int) round((float) $p['amount']) : null,
            providerRef: $p['reference_no'],
            failureReason: $p['error_message'] ?? null,
            raw: $p,
        );
    }
}
