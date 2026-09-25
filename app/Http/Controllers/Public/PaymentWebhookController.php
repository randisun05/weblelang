<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidWebhook;
use App\Payments\PaymentService;
use App\Payments\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Satu pintu webhook untuk semua gateway. Keaslian diverifikasi oleh driver masing-masing
 * (signature / callback token); route ini dikecualikan dari CSRF.
 */
class PaymentWebhookController extends Controller
{
    public function payment(Request $request, string $gateway, PaymentService $payments): JsonResponse
    {
        return $this->handle($request, $gateway, fn () => $payments->handleWebhook($gateway, $request));
    }

    public function payout(Request $request, string $gateway, PayoutService $payouts): JsonResponse
    {
        return $this->handle($request, $gateway, fn () => $payouts->handleWebhook($gateway, $request));
    }

    private function handle(Request $request, string $gateway, callable $process): JsonResponse
    {
        try {
            $process();
        } catch (GatewayException) {
            return response()->json(['message' => 'unknown gateway'], 404);
        } catch (InvalidWebhook $e) {
            Log::warning('Webhook ditolak', ['gateway' => $gateway, 'ip' => $request->ip(), 'reason' => $e->getMessage()]);

            return response()->json(['message' => 'rejected'], 403);
        }

        return response()->json(['message' => 'ok']);
    }
}
