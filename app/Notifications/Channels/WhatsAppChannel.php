<?php

namespace App\Notifications\Channels;

use App\WhatsApp\WhatsAppManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Channel notifikasi `whatsapp`. Notifikasi menyediakan toWhatsApp(); penerima menyediakan
 * routeNotificationForWhatsapp() (null = tidak mau/tidak punya nomor → dilewati).
 * Kegagalan penyedia dilempar ulang supaya job antrean mencoba lagi.
 */
class WhatsAppChannel
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $phone || ! $this->whatsapp->enabled() || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        if ($this->whatsapp->normalize($phone) === null) {
            Log::warning('[whatsapp] nomor tidak valid, pesan dilewati', ['notification' => $notification::class]);

            return;
        }

        $this->whatsapp->send($phone, $notification->toWhatsApp($notifiable));
    }
}
