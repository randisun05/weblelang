<?php

namespace App\Http\Controllers\User;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionRegistration;
use App\Models\Invoice;
use App\Models\Payment;
use App\Payments\Exceptions\GatewayException;
use App\Payments\GatewayEvent;
use App\Payments\PaymentService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    /** Bayar invoice pemenang → diarahkan ke halaman gateway. */
    public function payInvoice(Request $request, Invoice $invoice, PaymentService $payments): SymfonyResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        return $this->checkout(fn () => $payments->checkout($invoice, $request->user()));
    }

    /** Setor uang jaminan sesi → diarahkan ke halaman gateway; pendaftaran otomatis disetujui saat lunas. */
    public function payDeposit(Request $request, Auction $auction, PaymentService $payments): SymfonyResponse
    {
        abort_unless($auction->isPublic() && $auction->requiresRegistration(), 404);

        $registration = AuctionRegistration::firstOrCreate(['auction_id' => $auction->id, 'user_id' => $request->user()->id]);

        return $this->checkout(fn () => $payments->checkout($registration->setRelation('auction', $auction), $request->user()));
    }

    /** Halaman kembali dari gateway: menampilkan status sampai webhook masuk. */
    public function show(Request $request, Payment $payment): Response
    {
        $this->authorizeOwner($request, $payment);

        return Inertia::render('User/Payments/Show', [
            'payment' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'description' => $payment->description(),
                'gateway' => $payment->gateway,
                'method' => $payment->method,
                'status' => Present::status($payment->status),
                'checkout_url' => $payment->isReusable() ? $payment->checkout_url : null,
                'back_url' => $payment->payable instanceof Invoice
                    ? route('user.invoices.show', $payment->payable_id)
                    : route('auctions.show', $payment->payable->auction->slug),
            ],
        ]);
    }

    // ---- Simulator (hanya lokal/demo) -------------------------------------

    public function simulator(Request $request, Payment $payment): Response
    {
        $this->authorizeSimulator($request, $payment);

        return Inertia::render('User/Payments/Simulator', [
            'payment' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'description' => $payment->description(),
                'status' => Present::status($payment->status),
            ],
        ]);
    }

    public function simulate(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorizeSimulator($request, $payment);
        $data = $request->validate(['outcome' => ['required', 'in:paid,failed']]);

        $payments->process('simulator', new GatewayEvent(
            reference: $payment->reference,
            status: $data['outcome'],
            amount: $payment->amount,
            providerRef: 'SIM-'.$payment->id,
            method: 'simulator',
        ));

        return redirect()->route('payments.show', $payment->reference);
    }

    private function checkout(callable $create): SymfonyResponse
    {
        try {
            $payment = $create();
        } catch (GatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Inertia::location bekerja untuk request Inertia (XHR) maupun biasa.
        return Inertia::location($payment->checkout_url);
    }

    private function authorizeOwner(Request $request, Payment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 404);
    }

    private function authorizeSimulator(Request $request, Payment $payment): void
    {
        $this->authorizeOwner($request, $payment);
        abort_unless($payment->gateway === 'simulator' && ! app()->isProduction(), 404);
        abort_if($payment->status !== PaymentStatus::Pending, 410, 'Transaksi sudah diproses.');
    }
}
