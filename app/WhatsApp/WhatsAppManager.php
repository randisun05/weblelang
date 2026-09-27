<?php

namespace App\WhatsApp;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\Drivers\FonnteDriver;
use App\WhatsApp\Drivers\LogDriver;
use App\WhatsApp\Drivers\WablasDriver;

/** Memilih driver dari config/whatsapp.php (pola yang sama dengan PaymentManager). */
class WhatsAppManager
{
    /** @var array<string, class-string<WhatsAppGateway>> */
    private const DRIVERS = [
        'fonnte' => FonnteDriver::class,
        'wablas' => WablasDriver::class,
        'log' => LogDriver::class,
    ];

    public function enabled(): bool
    {
        return filled(config('whatsapp.driver'));
    }

    public function gateway(): WhatsAppGateway
    {
        $name = config('whatsapp.driver') ?: throw new WhatsAppException('Notifikasi WhatsApp belum diaktifkan.');
        $class = self::DRIVERS[$name] ?? throw new WhatsAppException("Driver WhatsApp [{$name}] tidak dikenal.");

        return app($class);
    }

    public function send(string $phone, string $message): ?string
    {
        $normalized = $this->normalize($phone) ?? throw new WhatsAppException("Nomor WhatsApp tidak valid: {$phone}");

        return $this->gateway()->send($normalized, $message);
    }

    /** 0812-3456-7890 / +62 812 3456 7890 / 6281234567890 → 6281234567890; null bila tidak valid. */
    public function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $country = (string) config('whatsapp.country_code', '62');

        if (str_starts_with($digits, '0')) {
            $digits = $country.substr($digits, 1);
        } elseif (str_starts_with($digits, '8') && $country === '62') {
            $digits = $country.$digits;
        }

        return preg_match('/^\d{10,15}$/', $digits) ? $digits : null;
    }
}
