<?php

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/** Untuk lokal/demo: pesan hanya ditulis ke log aplikasi. */
class LogDriver implements WhatsAppGateway
{
    public function send(string $phone, string $message): ?string
    {
        Log::info('[whatsapp] ke '.$phone, ['message' => $message]);

        return null;
    }
}
