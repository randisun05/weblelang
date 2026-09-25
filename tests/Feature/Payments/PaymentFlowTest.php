<?php

namespace Tests\Feature\Payments;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PaymentManager;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function wonInvoice(): Invoice
    {
        $lot = Lot::factory()->create(['reserve_price' => 0, 'starting_price' => 1_000_000]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        return $lot->fresh()->invoice;
    }

    // ---- Simulator (lokal/demo) -------------------------------------------

    public function test_invoice_paid_end_to_end_with_simulator(): void
    {
        config(['payments.gateway' => 'simulator']);
        $invoice = $this->wonInvoice();

        $response = $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice));
        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('payments.simulator', $payment->reference));
        $this->assertSame($invoice->total, $payment->amount);

        // Klik bayar lagi memakai sesi pembayaran yang sama.
        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice));
        $this->assertSame(1, Payment::count());

        $this->actingAs($invoice->user)->get(route('payments.simulator', $payment->reference))->assertOk();
        $this->actingAs($invoice->user)->post(route('payments.simulate', $payment->reference), ['outcome' => 'paid'])
            ->assertRedirect(route('payments.show', $payment->reference));

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertStringStartsWith('gateway:simulator', $invoice->fresh()->payment_method);
        $this->assertSame(1, Settlement::count());
        $this->actingAs($invoice->user)->get(route('payments.show', $payment->reference))->assertOk();
    }

    public function test_payments_are_private_to_their_owner(): void
    {
        config(['payments.gateway' => 'simulator']);
        $invoice = $this->wonInvoice();
        $intruder = User::factory()->verified()->create();

        $this->actingAs($intruder)->post(route('user.invoices.pay', $invoice))->assertNotFound();

        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice));
        $reference = Payment::value('reference');
        $this->actingAs($intruder)->get(route('payments.show', $reference))->assertNotFound();
        $this->actingAs($intruder)->post(route('payments.simulate', $reference), ['outcome' => 'paid'])->assertNotFound();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_deposit_paid_online_auto_approves_registration_and_allows_bidding(): void
    {
        config(['payments.gateway' => 'simulator']);
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        $lot->auction->update(['deposit_amount' => 2_000_000]);
        $bidder = User::factory()->verified()->create();

        $this->actingAs($bidder)->post(route('auctions.deposit.pay', $lot->auction->slug))->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame(2_000_000, $payment->amount);

        $this->actingAs($bidder)->post(route('payments.simulate', $payment->reference), ['outcome' => 'paid']);

        $registration = $lot->auction->registrations()->where('user_id', $bidder->id)->first();
        $this->assertSame(RegistrationStatus::Approved, $registration->status);
        $this->assertSame(DepositStatus::Held, $registration->deposit_status);

        app(BidService::class)->place($lot->fresh(), $bidder, 500_000);
        $this->assertSame($bidder->id, $lot->fresh()->leader_id);
    }

    public function test_failed_payment_leaves_invoice_unpaid(): void
    {
        config(['payments.gateway' => 'simulator']);
        $invoice = $this->wonInvoice();
        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice));

        $this->actingAs($invoice->user)->post(route('payments.simulate', Payment::value('reference')), ['outcome' => 'failed']);

        $this->assertSame(PaymentStatus::Failed, Payment::first()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_simulator_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(GatewayException::class);
        app(PaymentManager::class)->driver('simulator');
    }

    public function test_online_payment_can_be_disabled(): void
    {
        config(['payments.gateway' => null]);
        $invoice = $this->wonInvoice();

        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice))->assertSessionHas('error');
        $this->assertSame(0, Payment::count());
    }

    // ---- Midtrans ---------------------------------------------------------

    private function midtransPayment(Invoice $invoice): Payment
    {
        config(['payments.gateway' => 'midtrans', 'payments.drivers.midtrans.server_key' => 'SB-server']);
        Http::fake(['app.sandbox.midtrans.com/snap/*' => Http::response(['token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/tok'])]);

        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice))
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/tok');

        Http::assertSent(fn ($req) => $req['transaction_details']['gross_amount'] === $invoice->total
            && str_starts_with($req['transaction_details']['order_id'], 'PAY-'));

        return Payment::firstOrFail();
    }

    private function midtransNotification(Payment $payment, array $overrides = []): array
    {
        $data = array_merge([
            'order_id' => $payment->reference, 'status_code' => '200', 'gross_amount' => $payment->amount.'.00',
            'transaction_status' => 'settlement', 'payment_type' => 'bank_transfer', 'transaction_id' => 'trx-1',
        ], $overrides);
        $data['signature_key'] ??= hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].'SB-server');

        return $data;
    }

    public function test_midtrans_webhook_marks_paid_once(): void
    {
        $invoice = $this->wonInvoice();
        $payment = $this->midtransPayment($invoice);

        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment))->assertOk();
        // Webhook ganda (Midtrans bisa mengirim ulang) tidak membuat settlement ganda.
        $this->postJson(route('payments.midtrans.notification'), $this->midtransNotification($payment))->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('bank_transfer', $payment->fresh()->method);
        $this->assertSame(1, Settlement::count());
    }

    public function test_midtrans_webhook_rejects_forgery_and_wrong_amount(): void
    {
        $invoice = $this->wonInvoice();
        $payment = $this->midtransPayment($invoice);

        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment, ['signature_key' => str_repeat('a', 128)]))->assertForbidden();
        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment, ['gross_amount' => '1000.00']))->assertForbidden();
        // Dikirim ke endpoint gateway lain → ditolak.
        $this->postJson(route('payments.webhook', 'xendit'), ['external_id' => $payment->reference, 'status' => 'PAID'])->assertForbidden();
        $this->postJson(route('payments.webhook', 'paypal'), [])->assertNotFound();

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_midtrans_pending_and_expired_statuses(): void
    {
        $invoice = $this->wonInvoice();
        $payment = $this->midtransPayment($invoice);

        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment, ['transaction_status' => 'pending', 'status_code' => '201']))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment, ['transaction_status' => 'expire', 'status_code' => '407']))->assertOk();
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_money_received_for_cancelled_invoice_is_flagged_for_refund(): void
    {
        $invoice = $this->wonInvoice();
        $payment = $this->midtransPayment($invoice);
        app(InvoiceService::class)->cancel($invoice, 'Wanprestasi');

        $this->postJson(route('payments.webhook', 'midtrans'), $this->midtransNotification($payment))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.needs_refund', 'subject_id' => $payment->id]);
    }

    // ---- Xendit -----------------------------------------------------------

    public function test_xendit_invoice_checkout_and_callback(): void
    {
        config(['payments.gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd_test', 'payments.drivers.xendit.callback_token' => 'cb-token']);
        Http::fake(['api.xendit.co/v2/invoices' => Http::response(['id' => 'inv_123', 'invoice_url' => 'https://checkout-staging.xendit.co/web/inv_123'])]);
        $invoice = $this->wonInvoice();

        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice))->assertRedirect('https://checkout-staging.xendit.co/web/inv_123');
        $payment = Payment::firstOrFail();
        Http::assertSent(fn ($req) => $req['external_id'] === $payment->reference && $req['amount'] === $invoice->total);

        $body = ['id' => 'inv_123', 'external_id' => $payment->reference, 'status' => 'PAID', 'paid_amount' => $invoice->total, 'payment_channel' => 'QRIS'];
        $this->postJson(route('payments.webhook', 'xendit'), $body, ['x-callback-token' => 'wrong'])->assertForbidden();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);

        $this->postJson(route('payments.webhook', 'xendit'), $body, ['x-callback-token' => 'cb-token'])->assertOk();
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('QRIS', $payment->fresh()->method);
    }

    public function test_gateway_error_is_shown_without_creating_broken_checkout(): void
    {
        config(['payments.gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd_test']);
        Http::fake(['api.xendit.co/*' => Http::response(['message' => 'down'], 500)]);
        $invoice = $this->wonInvoice();

        $this->actingAs($invoice->user)->post(route('user.invoices.pay', $invoice))->assertSessionHas('error');
        $this->assertNull(Payment::first()->checkout_url);
        $this->assertSame(PaymentStatus::Failed, Payment::first()->status);
    }
}
