<?php

namespace App\Console\Commands;

use App\Notifications\AuctionNotification;
use App\WhatsApp\WhatsAppException;
use App\WhatsApp\WhatsAppManager;
use Illuminate\Console\Command;

/** Uji kredensial penyedia WhatsApp dengan mengirim satu pesan ke nomor tertentu. */
class WhatsAppTest extends Command
{
    protected $signature = 'whatsapp:test {phone : Nomor tujuan, mis. 081234567890}';

    protected $description = 'Kirim pesan uji WhatsApp lewat driver yang aktif (WHATSAPP_DRIVER)';

    public function handle(WhatsAppManager $whatsapp): int
    {
        if (! $whatsapp->enabled()) {
            $this->error('WHATSAPP_DRIVER belum diisi (fonnte, wablas, atau log).');

            return self::FAILURE;
        }

        $phone = $whatsapp->normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('Nomor tidak valid.');

            return self::FAILURE;
        }

        try {
            $id = $whatsapp->send($phone, AuctionNotification::whatsAppText('✅', 'Uji notifikasi WhatsApp',
                'Jika pesan ini sampai, notifikasi WhatsApp '.config('app.name').' sudah siap dipakai.', config('app.url')));
        } catch (WhatsAppException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Terkirim ke {$phone} via ".config('whatsapp.driver').($id ? " (id {$id})" : '').'.');

        return self::SUCCESS;
    }
}
