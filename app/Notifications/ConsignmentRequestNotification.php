<?php

namespace App\Notifications;

use App\Enums\ConsignmentRequestStatus;
use App\Models\ConsignmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/** Email ke calon penitip (belum tentu punya akun) tentang status pengajuannya. */
class ConsignmentRequestNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ConsignmentRequest $request)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $mail = (new MailMessage)->greeting("Halo {$r->name},");

        return match ($r->status) {
            ConsignmentRequestStatus::Accepted => $mail->subject("Pengajuan titip {$r->code} diterima")
                ->line("Barang \"{$r->title}\" kami terima untuk dilelang. Tim kami akan menghubungi Anda melalui {$r->phone} untuk jadwal "
                    .($r->handover === 'jemput' ? 'penjemputan' : 'pengantaran').', pemeriksaan, dan penandatanganan perjanjian titip.')
                ->action('Lihat status pengajuan', $r->statusUrl()),
            ConsignmentRequestStatus::Rejected => $mail->subject("Pengajuan titip {$r->code} belum dapat kami terima")
                ->line("Mohon maaf, barang \"{$r->title}\" belum dapat kami terima untuk dilelang.")
                ->line('Alasan: '.($r->reject_reason ?? '-'))
                ->line('Anda dapat mengajukan kembali barang lain kapan saja.'),
            default => $mail->subject("Pengajuan titip {$r->code} kami terima")
                ->line("Terima kasih! Pengajuan titip barang \"{$r->title}\" sudah kami terima dan akan ditinjau dalam 1–2 hari kerja.")
                ->action('Pantau status pengajuan', $r->statusUrl()),
        };
    }
}
