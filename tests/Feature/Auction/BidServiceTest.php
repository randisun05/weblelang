<?php

namespace Tests\Feature\Auction;

use App\Enums\RegistrationStatus;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidException;
use App\Services\Auction\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidServiceTest extends TestCase
{
    use RefreshDatabase;

    private BidService $bids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bids = app(BidService::class);
    }

    private function lot(array $attributes = []): Lot
    {
        return Lot::factory()->create($attributes + ['starting_price' => 1_000_000, 'reserve_price' => 1_500_000]);
    }

    public function test_first_bid_must_meet_starting_price(): void
    {
        $lot = $this->lot();
        $user = User::factory()->verified()->create();

        $this->expectException(BidException::class);
        $this->bids->place($lot, $user, 900_000);
    }

    public function test_bid_updates_lot_and_following_bid_must_add_increment(): void
    {
        $lot = $this->lot();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->bids->place($lot, $a, 1_000_000);
        $lot->refresh();

        $this->assertSame(1_000_000, $lot->current_price);
        $this->assertSame($a->id, $lot->leader_id);
        $this->assertSame(1, $lot->bids_count);

        try {
            $this->bids->place($lot, $b, 1_040_000); // kelipatan di level 1 jt = 50 rb
            $this->fail('Bid di bawah kelipatan seharusnya ditolak.');
        } catch (BidException) {
        }

        $this->bids->place($lot, $b, 1_050_000);
        $this->assertSame($b->id, $lot->fresh()->leader_id);
    }

    public function test_leader_cannot_outbid_themselves(): void
    {
        $lot = $this->lot();
        $a = User::factory()->verified()->create();
        $this->bids->place($lot, $a, 1_000_000);

        $this->expectExceptionMessage('sudah menjadi penawar tertinggi');
        $this->bids->place($lot, $a, 1_100_000);
    }

    public function test_unverified_blocked_and_staff_accounts_cannot_bid(): void
    {
        $lot = $this->lot();

        foreach ([
            User::factory()->create(),
            User::factory()->verified()->create(['is_blocked' => true]),
            User::factory()->verified()->admin()->create(),
        ] as $user) {
            try {
                $this->bids->place($lot, $user, 1_000_000);
                $this->fail('Bid seharusnya ditolak untuk '.$user->email);
            } catch (BidException) {
                $this->assertSame(0, $lot->fresh()->bids_count);
            }
        }
    }

    public function test_cannot_bid_on_ended_or_scheduled_lot(): void
    {
        $user = User::factory()->verified()->create();

        $ended = $this->lot(['ends_at' => now()->subSecond()]);
        $scheduled = $this->lot(['status' => 'scheduled', 'starts_at' => now()->addHour()]);

        foreach ([$ended, $scheduled] as $lot) {
            try {
                $this->bids->place($lot, $user, 1_000_000);
                $this->fail('Lot tidak aktif seharusnya menolak bid.');
            } catch (BidException) {
                $this->assertSame(0, $lot->fresh()->bids_count);
            }
        }
    }

    public function test_consignor_cannot_bid_on_own_item(): void
    {
        $lot = $this->lot();
        $user = User::factory()->verified()->create(['email' => $lot->item->consignor->email]);

        $this->expectExceptionMessage('Penitip');
        $this->bids->place($lot, $user, 1_000_000);
    }

    public function test_absurd_amount_is_rejected_as_typo(): void
    {
        $lot = $this->lot();
        $user = User::factory()->verified()->create();

        $this->expectExceptionMessage('terlalu jauh');
        $this->bids->place($lot, $user, 100_000_000);
    }

    public function test_deposit_auction_requires_approved_registration(): void
    {
        $lot = $this->lot();
        $lot->auction->update(['deposit_amount' => 1_000_000]);
        $user = User::factory()->verified()->create();

        try {
            $this->bids->place($lot->fresh(), $user, 1_000_000);
            $this->fail('Tanpa pendaftaran seharusnya ditolak.');
        } catch (BidException $e) {
            $this->assertStringContainsString('jaminan', $e->getMessage());
        }

        $registration = $lot->auction->registrations()->create(['user_id' => $user->id]);
        $registration->status = RegistrationStatus::Approved;
        $registration->save();

        $this->bids->place($lot->fresh(), $user, 1_000_000);
        $this->assertSame($user->id, $lot->fresh()->leader_id);
    }

    public function test_anti_sniping_extends_closing_time(): void
    {
        $lot = $this->lot(['ends_at' => now()->addMinute()]);
        $lot->auction->update(['anti_snipe_minutes' => 3, 'extend_minutes' => 3]);
        $user = User::factory()->verified()->create();

        $this->bids->place($lot->fresh(), $user, 1_000_000);
        $lot->refresh();

        $this->assertTrue($lot->ends_at->gt(now()->addMinutes(2)));
        $this->assertSame(1, $lot->extended_count);
    }

    public function test_bid_far_from_close_does_not_extend(): void
    {
        $lot = $this->lot(['ends_at' => now()->addHour()]);
        $end = $lot->ends_at->copy();

        $this->bids->place($lot, User::factory()->verified()->create(), 1_000_000);

        $this->assertTrue($lot->fresh()->ends_at->equalTo($end));
    }

    public function test_proxy_bid_defends_leader_with_minimum_increment(): void
    {
        $lot = $this->lot();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->bids->place($lot, $a, 1_000_000, 2_000_000);  // A pasang auto-bid max 2 jt
        $this->bids->place($lot, $b, 1_200_000);             // B menawar manual

        $lot->refresh();
        $this->assertSame($a->id, $lot->leader_id);
        $this->assertSame(1_250_000, $lot->current_price);    // 1,2 jt + kelipatan 50 rb
    }

    public function test_higher_proxy_beats_lower_proxy_by_one_increment(): void
    {
        $lot = $this->lot();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->bids->place($lot, $a, 1_000_000, 1_500_000);
        $this->bids->place($lot, $b, 1_050_000, 3_000_000);

        $lot->refresh();
        $this->assertSame($b->id, $lot->leader_id);
        $this->assertSame(1_550_000, $lot->current_price);
    }

    public function test_equal_proxy_max_goes_to_earlier_bidder(): void
    {
        $lot = $this->lot();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->bids->place($lot, $a, 1_000_000, 2_000_000);
        $this->travel(1)->seconds();
        $this->bids->place($lot, $b, 1_050_000, 2_000_000);

        $lot->refresh();
        $this->assertSame($a->id, $lot->leader_id);
        $this->assertSame(2_000_000, $lot->current_price);
    }

    public function test_leader_can_raise_proxy_limit_without_new_visible_bid(): void
    {
        $lot = $this->lot();
        $a = User::factory()->verified()->create();

        $this->bids->place($lot, $a, 1_000_000);
        $this->bids->place($lot, $a, 1_000_000, 5_000_000);

        $lot->refresh();
        $this->assertSame(1, $lot->bids_count);
        $this->assertSame(5_000_000, $lot->autoBids()->where('user_id', $a->id)->value('max_amount'));
    }

    public function test_bids_form_verifiable_hash_chain(): void
    {
        $lot = $this->lot();
        [$a, $b] = User::factory()->verified()->count(2)->create();

        $this->bids->place($lot, $a, 1_000_000);
        $this->bids->place($lot, $b, 1_050_000);
        $this->bids->place($lot, $a, 1_100_000);

        $prev = null;
        foreach (Bid::where('lot_id', $lot->id)->orderBy('id')->get() as $bid) {
            $this->assertSame($prev, $bid->prev_hash);
            $this->assertSame(
                Bid::computeHash($prev, $bid->lot_id, $bid->user_id, $bid->amount, $bid->created_at->format('Y-m-d H:i:s')),
                $bid->hash,
            );
            $prev = $bid->hash;
        }

        $this->assertNull(Bid::firstBrokenLink($lot->id));

        // Manipulasi nominal langsung di database harus terdeteksi.
        Bid::where('lot_id', $lot->id)->orderBy('id')->first()->update(['amount' => 999_000]);
        $this->assertNotNull(Bid::firstBrokenLink($lot->id));
    }
}
