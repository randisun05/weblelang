<?php

namespace App\WhatsApp\Contracts;

use App\WhatsApp\WhatsAppException;

interface WhatsAppGateway
{
    /**
     * Mengirim pesan teks. `$phone` sudah dinormalkan (mis. 6281234567890).
     *
     * @return string|null ID pesan dari penyedia (bila ada)
     *
     * @throws WhatsAppException bila penyedia menolak / gagal dihubungi
     */
    public function send(string $phone, string $message): ?string;
}
