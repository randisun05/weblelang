<?php

namespace App\Payments\Contracts;

use App\Models\Payout;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\GatewayEvent;
use Illuminate\Http\Request;

/** Gateway pengirim dana (disbursement / uang keluar). */
interface PayoutGateway
{
    public function name(): string;

    /** Mengirim instruksi transfer ke rekening tujuan. @throws GatewayException */
    public function createPayout(Payout $payout): GatewayEvent;

    /** Memverifikasi & menerjemahkan webhook status payout. @throws InvalidWebhook */
    public function parsePayoutWebhook(Request $request): GatewayEvent;
}
