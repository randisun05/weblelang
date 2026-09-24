<?php

namespace Tests\Feature\Auction;

use App\Enums\AuctionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Enums\SettlementStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_lots_open_when_start_time_passes(): void
    {
        $auction = Auction::factory()->create(['status' => AuctionStatus::Published, 'starts_at' => now()->subMinute()]);
        $lot = Lot::factory()->for($auction)->create(['status' => LotStatus::Scheduled, 'starts_at' => now()->subMinute()]);
        $draftLot = Lot::factory()->for(Auction::factory()->create(['status' => AuctionStatus::Draft]))
            ->create(['status' => LotStatus::Scheduled, 'starts_at' => now()->subMinute()]);

        $this->artisan('auctions:tick')->assertSuccessful();

        $this->assertSame(LotStatus::Live, $lot->fresh()->status);
        $this->assertSame(AuctionStatus::Live, $auction->fresh()->status);
        $this->assertSame(LotStatus::Scheduled, $draftLot->fresh()->status, 'Sesi draf tidak boleh dibuka.');
    }

    public function test_lot_meeting_reserve_is_sold_and_invoiced_with_buyer_premium(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 1_000_000, 'reserve_price' => 1_000_000]);
        $lot->auction->update(['buyer_premium_rate' => 5]);
        $winner = User::factory()->verified()->create();
        app(BidService::class)->place($lot, $winner, 2_000_000);

        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        $lot->refresh();
        $this->assertSame(LotStatus::Sold, $lot->status);
        $this->assertSame(ItemStatus::Sold, $lot->item->fresh()->status);
        $this->assertNotNull($lot->winning_bid_id);

        $invoice = $lot->invoice;
        $this->assertSame($winner->id, $invoice->user_id);
        $this->assertSame(2_000_000, $invoice->hammer_price);
        $this->assertSame(100_000, $invoice->buyer_premium);
        $this->assertSame(2_100_000, $invoice->total);
        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->assertSame(AuctionStatus::Closed, $lot->auction->fresh()->status);
    }

    public function test_lot_below_reserve_is_unsold_without_invoice(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000, 'reserve_price' => 5_000_000]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 500_000);

        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        $lot->refresh();
        $this->assertSame(LotStatus::Unsold, $lot->status);
        $this->assertSame(ItemStatus::Unsold, $lot->item->status);
        $this->assertNull($lot->invoice);
    }

    public function test_closing_is_idempotent(): void
    {
        $lot = Lot::factory()->create(['reserve_price' => 0]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 500_000);
        $this->travel(2)->days();

        $closer = app(LotCloser::class);
        $this->assertTrue($closer->close($lot->id));
        $this->assertFalse($closer->close($lot->id));
        $this->assertSame(1, $lot->invoice()->count());
    }

    public function test_paying_invoice_creates_consignor_settlement_minus_commission(): void
    {
        $lot = Lot::factory()->create(['reserve_price' => 0]);
        $lot->item->consignor->update(['commission_rate' => 10]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 3_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        $invoice = $lot->fresh()->invoice;
        $service = app(InvoiceService::class);
        $service->markPaid($invoice, 'transfer', 'REF-1');
        $service->markPaid($invoice, 'transfer', 'REF-1'); // idempoten

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(1, Settlement::count());

        $settlement = Settlement::first();
        $this->assertSame(300_000, $settlement->commission);
        $this->assertSame(2_700_000, $settlement->net_amount);
        $this->assertSame(SettlementStatus::Pending, $settlement->status);
    }

    public function test_item_commission_override_beats_consignor_rate(): void
    {
        $lot = Lot::factory()->create(['reserve_price' => 0]);
        $lot->item->update(['commission_rate' => 2.5]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 2_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        app(InvoiceService::class)->markPaid($lot->fresh()->invoice, 'transfer');

        $this->assertSame(50_000, Settlement::first()->commission);
    }

    public function test_cancelling_unpaid_invoice_returns_item_for_relisting(): void
    {
        $lot = Lot::factory()->create(['reserve_price' => 0]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 500_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        app(InvoiceService::class)->cancel($lot->fresh()->invoice, 'Wanprestasi');

        $this->assertSame(InvoiceStatus::Cancelled, $lot->fresh()->invoice->status);
        $this->assertSame(ItemStatus::Approved, $lot->item->fresh()->status);
    }
}
