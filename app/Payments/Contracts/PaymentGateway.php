<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\CheckoutResult;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\GatewayEvent;
use Illuminate\Http\Request;

/** Gateway penerima pembayaran (uang masuk). */
interface PaymentGateway
{
    public function name(): string;

    /** Membuat sesi pembayaran dan mengembalikan URL checkout gateway. @throws GatewayException */
    public function createCheckout(Payment $payment): CheckoutResult;

    /** Memverifikasi & menerjemahkan webhook pembayaran. @throws InvalidWebhook */
    public function parsePaymentWebhook(Request $request): GatewayEvent;
}
