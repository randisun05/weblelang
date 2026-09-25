<?php

namespace App\Payments\Drivers;

use App\Models\Payment;
use App\Models\Payout;
use App\Payments\CheckoutResult;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\PayoutGateway;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\GatewayEvent;
use Illuminate\Http\Request;

/**
 * Gateway tiruan untuk lokal/demo/pengujian. Checkout diarahkan ke halaman simulasi internal
 * dan payout langsung dianggap terkirim. PaymentManager menolak driver ini di production.
 */
class SimulatorDriver implements PaymentGateway, PayoutGateway
{
    public function name(): string
    {
        return 'simulator';
    }

    public function createCheckout(Payment $payment): CheckoutResult
    {
        return new CheckoutResult(route('payments.simulator', $payment->reference), 'SIM-'.$payment->id);
    }

    public function parsePaymentWebhook(Request $request): GatewayEvent
    {
        throw new InvalidWebhook('Simulator tidak menerima webhook.');
    }

    public function createPayout(Payout $payout): GatewayEvent
    {
        return new GatewayEvent(reference: $payout->reference, status: 'completed', providerRef: 'SIM-'.$payout->id);
    }

    public function parsePayoutWebhook(Request $request): GatewayEvent
    {
        throw new InvalidWebhook('Simulator tidak menerima webhook.');
    }
}
