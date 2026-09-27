<?php

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** https://wablas.com/documentation — POST {server}/api/send-message. */
class WablasDriver implements WhatsAppGateway
{
    public function send(string $phone, string $message): ?string
    {
        $token = config('whatsapp.wablas.token') ?: throw new WhatsAppException('WABLAS_TOKEN belum diisi.');
        $baseUrl = config('whatsapp.wablas.base_url') ?: throw new WhatsAppException('WABLAS_BASE_URL belum diisi (mis. https://tegal.wablas.com).');
        // Akun Wablas baru memakai "token.secret_key"; akun lama cukup token.
        $authorization = ($secret = config('whatsapp.wablas.secret_key')) ? "{$token}.{$secret}" : $token;

        try {
            $response = Http::withHeaders(['Authorization' => $authorization])->asForm()->timeout(15)
                ->post(rtrim($baseUrl, '/').'/api/send-message', ['phone' => $phone, 'message' => $message]);
        } catch (ConnectionException $e) {
            throw new WhatsAppException('Wablas tidak dapat dihubungi: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw new WhatsAppException('Wablas menolak pesan: '.($response->json('message') ?? $response->body()));
        }

        return $response->json('data.messages.0.id') ?? $response->json('data.id');
    }
}
