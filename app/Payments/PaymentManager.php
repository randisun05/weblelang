<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\PayoutGateway;
use App\Payments\Drivers\MidtransDriver;
use App\Payments\Drivers\SimulatorDriver;
use App\Payments\Drivers\XenditDriver;
use App\Payments\Exceptions\GatewayException;

/**
 * Memilih driver gateway dari config/payments.php. Tambah gateway baru cukup dengan
 * membuat kelas driver yang mengimplementasikan kontrak lalu mendaftarkannya di sini.
 */
class PaymentManager
{
    /** @var array<string, class-string> */
    private const DRIVERS = [
        'midtrans' => MidtransDriver::class,
        'xendit' => XenditDriver::class,
        'simulator' => SimulatorDriver::class,
    ];

    public function paymentsEnabled(): bool
    {
        return filled(config('payments.gateway'));
    }

    public function payoutsEnabled(): bool
    {
        return filled(config('payments.payout_gateway'));
    }

    public function paymentGateway(): PaymentGateway
    {
        return $this->driver(config('payments.gateway') ?: throw new GatewayException('Pembayaran online belum diaktifkan.'));
    }

    public function payoutGateway(): PayoutGateway
    {
        return $this->driver(config('payments.payout_gateway') ?: throw new GatewayException('Transfer otomatis (payout) belum diaktifkan.'));
    }

    public function driver(string $name): PaymentGateway&PayoutGateway
    {
        $class = self::DRIVERS[$name] ?? throw new GatewayException("Gateway [{$name}] tidak dikenal.");

        if ($name === 'simulator' && app()->isProduction()) {
            throw new GatewayException('Gateway simulator tidak boleh dipakai di production.');
        }

        return app($class);
    }
}
