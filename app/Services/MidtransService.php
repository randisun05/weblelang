<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Pola sama dengan web-aspro: Snap token + verifikasi signature notifikasi. */
class MidtransService
{
    public function isConfigured(): bool
    {
        return (bool) config('midtrans.server_key');
    }

    private function baseUrl(): string
    {
        return config('midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /** @return array{token: string, redirect_url: string} */
    public function createSnapToken(Invoice $invoice): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diatur.');
        }

        $response = Http::withBasicAuth(config('midtrans.server_key'), '')
            ->acceptJson()
            ->post($this->baseUrl(), [
                'transaction_details' => [
                    'order_id' => $invoice->number,
                    'gross_amount' => $invoice->total,
                ],
                'customer_details' => [
                    'first_name' => $invoice->user->name,
                    'email' => $invoice->user->email,
                    'phone' => $invoice->user->phone,
                ],
                'item_details' => [[
                    'id' => 'LOT-'.$invoice->lot_id,
                    'price' => $invoice->total,
                    'quantity' => 1,
                    'name' => mb_substr($invoice->lot->item->title, 0, 50),
                ]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal membuat transaksi Midtrans.');
        }

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    /** https://docs.midtrans.com/docs/https-notification-webhooks */
    public function verifySignature(array $payload): bool
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '' || ! isset($payload['signature_key'], $payload['order_id'], $payload['status_code'], $payload['gross_amount'])) {
            return false;
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    public function isSuccessful(array $payload): bool
    {
        $status = $payload['transaction_status'] ?? null;

        return $status === 'settlement'
            || ($status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept');
    }
}
