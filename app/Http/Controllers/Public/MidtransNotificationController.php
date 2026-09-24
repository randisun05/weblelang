<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans, InvoiceService $invoices): JsonResponse
    {
        $payload = $request->all();

        if (! $midtrans->verifySignature($payload)) {
            Log::warning('Midtrans: signature tidak valid', ['order_id' => $payload['order_id'] ?? null, 'ip' => $request->ip()]);

            return response()->json(['message' => 'invalid signature'], 403);
        }

        $invoice = Invoice::where('number', $payload['order_id'])->first();

        if (! $invoice) {
            return response()->json(['message' => 'not found'], 404);
        }

        // Nominal harus sama persis dengan tagihan.
        if ((int) round((float) $payload['gross_amount']) !== $invoice->total) {
            Log::warning('Midtrans: nominal tidak cocok', ['invoice' => $invoice->number]);

            return response()->json(['message' => 'amount mismatch'], 422);
        }

        if ($midtrans->isSuccessful($payload)) {
            try {
                $invoices->markPaid($invoice, 'midtrans:'.($payload['payment_type'] ?? 'unknown'), $payload['transaction_id'] ?? null);
            } catch (\RuntimeException $e) {
                // Mis. invoice sudah dibatalkan: catat untuk refund manual, jangan minta Midtrans mengulang.
                Log::error('Midtrans: pembayaran untuk invoice non-aktif', ['invoice' => $invoice->number, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'ok']);
    }
}
