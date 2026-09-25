<?php

namespace Tests\Feature\Auction;

use App\Enums\AuctionMethod;
use App\Enums\AuctionStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Enums\Role;
use App\Models\Auction;
use App\Models\Item;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidException;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function lot(AuctionMethod $method, array $lot = [], array $auction = []): Lot
    {
        $session = Auction::factory()->create(['method' => $method] + $auction);

        return Lot::factory()->for($session)->create($lot + ['starting_price' => 1_000_000, 'reserve_price' => 1_500_000]);
    }

    // ---- Penawaran tertutup ----------------------------------------------

    public function test_sealed_bids_are_hidden_from_everyone_until_close(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed);
        [$a, $b] = User::factory()->verified()->count(2)->create();
        $admin = User::factory()->role(Role::SuperAdmin)->create();

        app(BidService::class)->place($lot, $a, 7_777_000);
        app(BidService::class)->place($lot, $b, 1_200_000); // lebih rendah pun tetap diterima

        $lot->refresh();
        $this->assertSame(0, $lot->current_price);
        $this->assertNull($lot->leader_id);
        $this->assertSame(2, $lot->bids_count);

        foreach ([
            $this->getJson(route('lots.state', $lot)),
            $this->actingAs($b)->getJson(route('lots.state', $lot)),
            $this->actingAs($admin)->get(route('admin.auctions.show', $lot->auction)),
            $this->actingAs($admin)->get(route('admin.dashboard')),
            $this->actingAs($admin)->get(route('admin.bidders.show', $a)),
            $this->get($lot->item->consignor->portalUrl()),
            $this->get(route('auctions.show', $lot->auction->slug)),
        ] as $response) {
            $response->assertOk();
            $this->assertStringNotContainsString('7777000', $response->getContent(), 'Nominal penawaran tertutup bocor.');
        }

        $this->actingAs($a)->getJson(route('lots.state', $lot))
            ->assertJsonPath('my_bid', 7_777_000)
            ->assertJsonPath('concealed', true)
            ->assertJsonPath('bids', [])
            ->assertJsonPath('bids_count', null);
    }

    public function test_sealed_bid_can_be_revised_and_final_bids_decide_winner(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed);
        [$a, $b] = User::factory()->verified()->count(2)->create();
        $bids = app(BidService::class);

        $bids->place($lot, $a, 3_000_000);
        $bids->place($lot, $b, 2_500_000);
        $bids->place($lot, $a, 2_000_000); // A menurunkan penawarannya → yang final dihitung

        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        $lot->refresh();
        $this->assertSame(LotStatus::Sold, $lot->status);
        $this->assertSame($b->id, $lot->leader_id);
        $this->assertSame(2_500_000, $lot->current_price);
        $this->assertSame(2_500_000, $lot->invoice->hammer_price);

        // Setelah ditutup, hanya penawaran final yang ditampilkan.
        $amounts = collect($this->getJson(route('lots.state', $lot))->json('bids'))->pluck('amount')->all();
        $this->assertSame([2_500_000, 2_000_000], $amounts);
    }

    public function test_sealed_tie_goes_to_earlier_final_bid_and_reserve_still_applies(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed);
        [$a, $b] = User::factory()->verified()->count(2)->create();

        app(BidService::class)->place($lot, $a, 2_000_000);
        app(BidService::class)->place($lot, $b, 2_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();
        $this->assertSame($a->id, $lot->fresh()->leader_id);

        $low = $this->lot(AuctionMethod::Sealed, ['ends_at' => now()->addHour()]);
        app(BidService::class)->place($low, $b, 1_100_000); // di bawah limit 1,5 jt
        $this->travel(2)->hours();
        app(LotCloser::class)->closeDue();
        $this->assertSame(LotStatus::Unsold, $low->fresh()->status);
    }

    public function test_sealed_rejects_below_start_autobid_and_duplicate(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed);
        $a = User::factory()->verified()->create();
        $bids = app(BidService::class);

        foreach ([fn () => $bids->place($lot, $a, 900_000), fn () => $bids->place($lot, $a, 1_500_000, 2_000_000)] as $attempt) {
            try {
                $attempt();
                $this->fail('Seharusnya ditolak.');
            } catch (BidException) {
            }
        }

        $bids->place($lot, $a, 1_500_000);
        $this->expectExceptionMessage('sama');
        $bids->place($lot, $a, 1_500_000);
    }

    public function test_sealed_bids_do_not_extend_time(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed, ['ends_at' => now()->addMinute()]);
        $end = $lot->ends_at->copy();

        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);

        $this->assertTrue($lot->fresh()->ends_at->equalTo($end));
    }

    // ---- Beli Langsung ---------------------------------------------------

    public function test_buy_now_sells_immediately_and_invoices_buyer(): void
    {
        $lot = $this->lot(AuctionMethod::Open, ['buy_now_price' => 5_000_000]);
        $buyer = User::factory()->verified()->create();

        $this->getJson(route('lots.state', $lot))->assertJsonPath('buy_now_price', 5_000_000);

        $this->actingAs($buyer)->post(route('lots.buy-now', $lot))->assertRedirect()->assertSessionHas('success');

        $lot->refresh();
        $this->assertSame(LotStatus::Sold, $lot->status);
        $this->assertSame('buy_now', $lot->sold_via);
        $this->assertSame($buyer->id, $lot->leader_id);
        $this->assertSame(5_000_000, $lot->invoice->hammer_price);
        $this->assertSame(ItemStatus::Sold, $lot->item->status);

        // Tidak bisa dibeli dua kali / ditawar lagi.
        $this->actingAs(User::factory()->verified()->create())->post(route('lots.buy-now', $lot))->assertSessionHas('error');
    }

    public function test_buy_now_disappears_after_first_bid_and_is_open_method_only(): void
    {
        $lot = $this->lot(AuctionMethod::Open, ['buy_now_price' => 5_000_000]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);

        $this->getJson(route('lots.state', $lot))->assertJsonPath('buy_now_price', null);
        $this->actingAs(User::factory()->verified()->create())->post(route('lots.buy-now', $lot))->assertSessionHas('error');

        $sealed = $this->lot(AuctionMethod::Sealed, ['buy_now_price' => 5_000_000]);
        $this->actingAs(User::factory()->verified()->create())->post(route('lots.buy-now', $sealed))->assertSessionHas('error');
        $this->assertSame(LotStatus::Live, $sealed->fresh()->status);
    }

    public function test_admin_buy_now_price_must_cover_reserve(): void
    {
        $admin = User::factory()->admin()->create();
        $auction = Auction::factory()->create(['status' => AuctionStatus::Draft, 'method' => AuctionMethod::Open]);
        $item = Item::factory()->create(['reserve_price' => 3_000_000]);

        $this->actingAs($admin)->post(route('admin.auctions.lots.store', $auction), [
            'lots' => [['item_id' => $item->id, 'starting_price' => 1_000_000, 'buy_now_price' => 2_000_000]],
        ])->assertSessionHasErrors('lots');
        $this->assertSame(0, $auction->lots()->count());

        $this->actingAs($admin)->post(route('admin.auctions.lots.store', $auction), [
            'lots' => [['item_id' => $item->id, 'starting_price' => 1_000_000, 'buy_now_price' => 4_000_000]],
        ])->assertSessionHas('success');
        $this->assertSame(4_000_000, $auction->lots()->first()->buy_now_price);
    }

    // ---- Live juru lelang ------------------------------------------------

    public function test_live_lots_are_not_opened_or_closed_by_the_scheduler(): void
    {
        $lot = $this->lot(AuctionMethod::Live, ['status' => LotStatus::Scheduled, 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinute()], ['status' => AuctionStatus::Published]);

        $this->artisan('auctions:tick');
        $this->get(route('lots.show', $lot))->assertOk();

        $this->assertSame(LotStatus::Scheduled, $lot->fresh()->status);
    }

    public function test_auctioneer_flow_open_calls_reset_by_bid_then_hammer(): void
    {
        $auction = Auction::factory()->create(['method' => AuctionMethod::Live, 'status' => AuctionStatus::Published]);
        [$lot1, $lot2] = Lot::factory()->for($auction)->count(2)->create(['status' => LotStatus::Scheduled, 'starting_price' => 1_000_000, 'reserve_price' => 0]);
        $admin = User::factory()->admin()->create();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->actingAs($admin)->get(route('admin.auctions.live', $auction))->assertOk();
        $this->actingAs($admin)->post(route('admin.auctions.live.open', [$auction, $lot1]))->assertSessionHas('success');
        $this->assertSame(LotStatus::Live, $lot1->fresh()->status);
        // Hanya satu lot live dalam satu waktu.
        $this->actingAs($admin)->post(route('admin.auctions.live.open', [$auction, $lot2]))->assertSessionHas('error');

        app(BidService::class)->place($lot1->fresh(), $a, 1_000_000);
        $this->actingAs($admin)->post(route('admin.auctions.live.call', [$auction, $lot1]));
        $this->actingAs($admin)->post(route('admin.auctions.live.hammer', [$auction, $lot1]))->assertSessionHas('error'); // baru panggilan 1
        $this->actingAs($admin)->post(route('admin.auctions.live.call', [$auction, $lot1]));
        $this->assertSame(2, $lot1->fresh()->live_calls);

        // Bid di detik terakhir membatalkan panggilan → palu ditolak.
        app(BidService::class)->place($lot1->fresh(), $b, 1_050_000);
        $this->assertSame(0, $lot1->fresh()->live_calls);
        $this->actingAs($admin)->post(route('admin.auctions.live.hammer', [$auction, $lot1]))->assertSessionHas('error');

        $this->actingAs($admin)->post(route('admin.auctions.live.call', [$auction, $lot1]));
        $this->actingAs($admin)->post(route('admin.auctions.live.call', [$auction, $lot1]));
        $this->actingAs($admin)->post(route('admin.auctions.live.hammer', [$auction, $lot1]))->assertSessionHas('success');

        $lot1->refresh();
        $this->assertSame(LotStatus::Sold, $lot1->status);
        $this->assertSame('live', $lot1->sold_via);
        $this->assertSame($b->id, $lot1->invoice->user_id);

        $this->actingAs(User::factory()->staff()->create())->post(route('admin.auctions.live.open', [$auction, $lot2]))->assertForbidden();
    }

    public function test_live_bids_do_not_trigger_anti_sniping(): void
    {
        $lot = $this->lot(AuctionMethod::Live, ['ends_at' => now()->addMinute()]);
        $end = $lot->ends_at->copy();

        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);

        $this->assertTrue($lot->fresh()->ends_at->equalTo($end));
    }

    public function test_stream_embed_only_for_known_providers(): void
    {
        $auction = new Auction;

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'https://www.youtube.com/live/dQw4w9WgXcQ?si=x' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'https://vimeo.com/123456' => 'https://player.vimeo.com/video/123456',
            'https://evil.example.com/stream' => null,
        ] as $url => $expected) {
            $auction->stream_url = $url;
            $this->assertSame($expected, $auction->streamEmbedUrl(), $url);
        }
    }

    public function test_method_cannot_change_after_bids(): void
    {
        $lot = $this->lot(AuctionMethod::Open, [], ['status' => AuctionStatus::Published]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);
        $auction = $lot->auction;

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.auctions.update', $auction), [
            'title' => $auction->title, 'starts_at' => $auction->starts_at->toDateTimeString(), 'ends_at' => $auction->ends_at->toDateTimeString(),
            'deposit_amount' => 0, 'buyer_premium_rate' => 5, 'anti_snipe_minutes' => 3, 'extend_minutes' => 3, 'stagger_seconds' => 0,
            'method' => 'sealed',
        ])->assertSessionHas('error');

        $this->assertSame(AuctionMethod::Open, $auction->fresh()->method);
    }
}
