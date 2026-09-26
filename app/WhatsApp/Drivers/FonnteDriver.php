<?php

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** https://docs.fonnte.com — POST /send dengan header Authorization: <token>. */
class FonnteDriver implements WhatsAppGateway
{
    public function send(string $phone, string $message): ?string
    {
        $token = config('whatsapp.fonnte.token') ?: throw new WhatsAppException('FONNTE_TOKEN belum diisi.');

        try {
            $response = Http::withHeaders(['Authorization' => $token])->asForm()->timeout(15)
                ->post(rtrim(config('whatsapp.fonnte.base_url'), '/').'/send', [
                    'target' => $phone,
                    'message' => $message,
                    'countryCode' => '0', // nomor sudah dinormalkan dengan kode negara
                ]);
        } catch (ConnectionException $e) {
            throw new WhatsAppException('Fonnte tidak dapat dihubungi: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw new WhatsAppException('Fonnte menolak pesan: '.($response->json('reason') ?? $response->body()));
        }

        $id = $response->json('id');

        return is_array($id) ? (string) ($id[0] ?? '') ?: null : ($id !== null ? (string) $id : null);
    }
}
