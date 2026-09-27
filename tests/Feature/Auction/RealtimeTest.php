<?php

namespace Tests\Feature\Auction;

use App\Enums\AuctionMethod;
use App\Enums\AuctionStatus;
use App\Enums\LotStatus;
use App\Events\LotUpdated;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\OutbidNotification;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function lot(AuctionMethod $method, array $lot = [], array $auction = []): Lot
    {
        $session = Auction::factory()->create(['method' => $method] + $auction);

        return Lot::factory()->for($session)->create($lot + ['starting_price' => 1_000_000, 'reserve_price' => 0]);
    }

    public function test_bid_and_close_broadcast_a_signal_without_price_data(): void
    {
        Event::fake([LotUpdated::class]);
        $lot = $this->lot(AuctionMethod::Open);

        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);

        Event::assertDispatched(LotUpdated::class, function (LotUpdated $e) use ($lot) {
            $this->assertSame(['lot_id' => $lot->id, 'reason' => 'bid'], $e->broadcastWith());
            $this->assertSame(['lots.'.$lot->id, 'auctions.'.$lot->auction_id], array_map(fn (Channel $c) => $c->name, $e->broadcastOn()));
            $this->assertTrue($e->broadcastWhen());

            return $e->reason === LotUpdated::BID;
        });

        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        Event::assertDispatched(LotUpdated::class, fn (LotUpdated $e) => $e->reason === LotUpdated::CLOSED && $e->lotId === $lot->id);
    }

    public function test_sealed_bids_are_never_broadcast(): void
    {
        Event::fake([LotUpdated::class]);
        $lot = $this->lot(AuctionMethod::Sealed);

        app(BidService::class)->place($lot, User::factory()->verified()->create(), 2_000_000);

        Event::assertDispatched(LotUpdated::class, fn (LotUpdated $e) => $e->reason === LotUpdated::BID && ! $e->broadcastWhen());

        // Penutupan lot tertutup (pengumuman pemenang) boleh disiarkan.
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();
        Event::assertDispatched(LotUpdated::class, fn (LotUpdated $e) => $e->reason === LotUpdated::CLOSED && $e->broadcastWhen());
    }

    public function test_scheduler_opening_and_auctioneer_actions_are_broadcast(): void
    {
        Event::fake([LotUpdated::class]);

        $scheduled = $this->lot(AuctionMethod::Open, ['status' => LotStatus::Scheduled, 'starts_at' => now()->subMinute()], ['status' => AuctionStatus::Published]);
        app(LotCloser::class)->openDue();
        Event::assertDispatched(LotUpdated::class, fn (LotUpdated $e) => $e->reason === LotUpdated::OPENED && $e->lotId === $scheduled->id);

        $live = $this->lot(AuctionMethod::Live, ['status' => LotStatus::Scheduled], ['status' => AuctionStatus::Published]);
        $closer = app(LotCloser::class);
        $closer->openLive($live);
        $closer->callLive($live);
        $closer->callLive($live);
        $closer->hammer($live);

        foreach ([LotUpdated::OPENED, LotUpdated::CALL, LotUpdated::CLOSED] as $reason) {
            Event::assertDispatched(LotUpdated::class, fn (LotUpdated $e) => $e->reason === $reason && $e->lotId === $live->id);
        }
        Event::assertDispatchedTimes(LotUpdated::class, 5);
    }

    public function test_notifications_use_broadcast_channel_only_when_websocket_is_enabled(): void
    {
        $lot = $this->lot(AuctionMethod::Open);
        $user = User::factory()->verified()->create();
        $notification = new OutbidNotification($lot, 1_000_000);

        config(['broadcasting.default' => 'null']);
        $this->assertNotContains('broadcast', $notification->via($user));

        config(['broadcasting.default' => 'reverb']);
        $this->assertContains('broadcast', $notification->via($user));
    }
}
