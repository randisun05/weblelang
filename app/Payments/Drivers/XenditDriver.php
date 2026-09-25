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

/**
 * Xendit: Invoice (pembayaran) + Disbursement (payout).
 * Docs: https://developers.xendit.co/api-reference/#create-invoice  &  #create-disbursement
 */
class XenditDriver implements PaymentGateway, PayoutGateway
{
    private const BASE = 'https://api.xendit.co';

    public function name(): string
    {
        return 'xendit';
    }

    private function http()
    {
        $key = config('payments.drivers.xendit.secret_key') ?: throw new GatewayException('XENDIT_SECRET_KEY belum diatur.');

        return Http::withBasicAuth($key, '')->acceptJson()->timeout(20);
    }

    public function createCheckout(Payment $payment): CheckoutResult
    {
        try {
            $response = $this->http()->post(self::BASE.'/v2/invoices', [
                'external_id' => $payment->reference,
                'amount' => $payment->amount,
                'description' => $payment->description(),
                'payer_email' => $payment->user->email,
                'customer' => [
                    'given_names' => $payment->user->name,
                    'email' => $payment->user->email,
                    'mobile_number' => $payment->user->phone,
                ],
                'invoice_duration' => max(60, now()->diffInSeconds($payment->expires_at)),
                'success_redirect_url' => $payment->returnUrl(),
                'failure_redirect_url' => $payment->returnUrl(),
                'currency' => 'IDR',
            ]);
        } catch (ConnectionException) {
            throw new GatewayException('Tidak dapat terhubung ke Xendit.');
        }

        if ($response->failed() || ! $response->json('invoice_url')) {
            throw new GatewayException('Xendit menolak pembuatan invoice.');
        }

        return new CheckoutResult($response->json('invoice_url'), $response->json('id'));
    }

    public function parsePaymentWebhook(Request $request): GatewayEvent
    {
        $this->verifyToken($request);
        $p = $request->all();

        if (! isset($p['external_id'], $p['status'])) {
            throw new InvalidWebhook('Payload Xendit tidak lengkap.');
        }

        return new GatewayEvent(
            reference: $p['external_id'],
            status: match ($p['status']) {
                'PAID', 'SETTLED' => 'paid',
                'EXPIRED' => 'expired',
                default => 'pending',
            },
            amount: (int) ($p['paid_amount'] ?? $p['amount'] ?? 0),
            providerRef: $p['id'] ?? null,
            method: $p['payment_channel'] ?? $p['payment_method'] ?? null,
            raw: $p,
        );
    }

    public function createPayout(Payout $payout): GatewayEvent
    {
        try {
            $response = $this->http()
                ->withHeaders(['X-IDEMPOTENCY-KEY' => $payout->reference])
                ->post(self::BASE.'/disbursements', [
                    'external_id' => $payout->reference,
                    'amount' => $payout->amount,
                    'bank_code' => $payout->bank_code,
                    'account_holder_name' => $payout->account_holder,
                    'account_number' => $payout->account_number,
                    'description' => $payout->reference,
                ]);
        } catch (ConnectionException) {
            throw new GatewayException('Tidak dapat terhubung ke Xendit.');
        }

        if ($response->failed() || ! $response->json('id')) {
            throw new GatewayException('Xendit menolak disbursement: '.($response->json('message') ?? 'unknown error'));
        }

        return new GatewayEvent(reference: $payout->reference, status: 'processing', providerRef: $response->json('id'), raw: $response->json());
    }

    public function parsePayoutWebhook(Request $request): GatewayEvent
    {
        $this->verifyToken($request);
        $p = $request->all();

        if (! isset($p['external_id'], $p['status'])) {
            throw new InvalidWebhook('Payload Xendit tidak lengkap.');
        }

        return new GatewayEvent(
            reference: $p['external_id'],
            status: match ($p['status']) {
                'COMPLETED' => 'completed',
                'FAILED' => 'failed',
                default => 'processing',
            },
            amount: isset($p['amount']) ? (int) $p['amount'] : null,
            providerRef: $p['id'] ?? null,
            failureReason: $p['failure_code'] ?? null,
            raw: $p,
        );
    }

    private function verifyToken(Request $request): void
    {
        $token = (string) config('payments.drivers.xendit.callback_token');

        if ($token === '' || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            throw new InvalidWebhook('Callback token Xendit tidak valid.');
        }
    }
}
