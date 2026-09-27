<?php

namespace App\Notifications;

use App\Models\Settlement;
use App\WhatsApp\WhatsAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Kabar untuk penitip (tidak punya akun): barangnya terjual & lunas, lalu saat dana sudah ditransfer.
 * Dikirim ke email penitip (bila ada) dan WhatsApp; berisi link portal penitip.
 */
class ConsignorSettlementNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const SOLD = 'sold';

    public const PAID = 'paid';

    /** @param  string  $event  self::SOLD saat invoice pembeli lunas, self::PAID saat dana ditransfer */
    public function __construct(public Settlement $settlement, public string $event)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            $notifiable->routeNotificationFor('mail', $this) ? 'mail' : null,
            app(WhatsAppManager::class)->enabled() ? 'whatsapp' : null,
        ]));
    }

    /** @return array{0: string, 1: string, 2: string} icon, judul, isi */
    private function content(): array
    {
        $s = $this->settlement->loadMissing('invoice.lot.item', 'consignor');
        $item = $s->invoice->lot->item->title;
        $rp = fn (int $v) => 'Rp '.number_format($v, 0, ',', '.');

        return $this->event === self::PAID
            ? ['💸', 'Dana hasil lelang sudah ditransfer',
                "Halo {$s->consignor->name}, dana hasil lelang \"{$item}\" sebesar {$rp($s->net_amount)} sudah kami transfer ke rekening Anda."]
            : ['🎉', 'Barang titipan Anda terjual',
                "Halo {$s->consignor->name}, \"{$item}\" terjual {$rp($s->hammer_price)} dan pembeli sudah melunasi. "
                ."Setelah komisi {$s->commission_rate}% ({$rp($s->commission)}), dana bersih {$rp($s->net_amount)} akan segera kami transfer."];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [, $title, $body] = $this->content();

        return (new MailMessage)->subject($title)->line($body)
            ->action('Lihat portal penitip', $this->settlement->consignor->portalUrl())
            ->salutation('Salam, '.config('app.name'));
    }

    public function toWhatsApp(object $notifiable): string
    {
        [$icon, $title, $body] = $this->content();

        return AuctionNotification::whatsAppText($icon, $title, $body, $this->settlement->consignor->portalUrl());
    }
}
