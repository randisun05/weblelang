<?php

namespace Tests\Feature\Http;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['midtrans.server_key' => 'SB-Mid-server-test']);

        $lot = Lot::factory()->create(['reserve_price' => 0]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();
        $this->invoice = $lot->fresh()->invoice;
    }

    private function payload(array $overrides = []): array
    {
        $data = array_merge([
            'order_id' => $this->invoice->number,
            'status_code' => '200',
            'gross_amount' => $this->invoice->total.'.00',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-123',
        ], $overrides);
        $data['signature_key'] ??= hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].'SB-Mid-server-test');

        return $data;
    }

    public function test_valid_notification_marks_invoice_paid(): void
    {
        $this->postJson(route('payments.midtrans.notification'), $this->payload())->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $this->invoice->fresh()->status);
        $this->assertNotNull($this->invoice->fresh()->settlement);
    }

    public function test_forged_signature_is_rejected(): void
    {
        $this->postJson(route('payments.midtrans.notification'), $this->payload(['signature_key' => str_repeat('a', 128)]))->assertForbidden();

        $this->assertSame(InvoiceStatus::Unpaid, $this->invoice->fresh()->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->postJson(route('payments.midtrans.notification'), $this->payload(['gross_amount' => '1000.00']))->assertStatus(422);

        $this->assertSame(InvoiceStatus::Unpaid, $this->invoice->fresh()->status);
    }

    public function test_pending_status_does_not_mark_paid(): void
    {
        $this->postJson(route('payments.midtrans.notification'), $this->payload(['transaction_status' => 'pending', 'status_code' => '201']))->assertOk();

        $this->assertSame(InvoiceStatus::Unpaid, $this->invoice->fresh()->status);
    }
}
