<?php

namespace App\Notifications;

use App\WhatsApp\WhatsAppManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Dasar semua notifikasi: dikirim ke email dan disimpan di database (lonceng in-app).
 * Isi cukup didefinisikan sekali lewat title()/body()/url().
 * Antrean (queue) dijalankan setelah transaksi DB selesai.
 */
abstract class AuctionNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function url(): string;

    protected function icon(): string
    {
        return '🔔';
    }

    protected function actionText(): string
    {
        return 'Lihat detail';
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($this->sendsWhatsApp() && app(WhatsAppManager::class)->enabled()) {
            $channels[] = 'whatsapp';
        }

        // Lonceng real-time di browser bila websocket (Reverb/Pusher) aktif.
        if (in_array(config('broadcasting.default'), ['reverb', 'pusher', 'ably'], true)) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->body())
            ->action($this->actionText(), $this->url())
            ->salutation('Salam, '.config('app.name'));
    }

    /** Matikan per jenis notifikasi bila tidak perlu dikirim lewat WhatsApp. */
    protected function sendsWhatsApp(): bool
    {
        return true;
    }

    public function toWhatsApp(object $notifiable): string
    {
        return self::whatsAppText($this->icon(), $this->title(), $this->body(), $this->url());
    }

    /** Format pesan WA seragam: judul tebal, isi, tautan, nama situs. */
    public static function whatsAppText(string $icon, string $title, string $body, ?string $url = null): string
    {
        return trim("{$icon} *{$title}*\n\n{$body}".($url ? "\n\n{$url}" : '')."\n\n_".config('app.name').'_');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }
}
