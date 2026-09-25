<?php

namespace Tests\Feature\Http;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        $lot = Lot::factory()->create();

        $this->get('/')->assertOk();
        $this->get('/lelang')->assertOk();
        $this->get('/cara-kerja')->assertOk();
        $this->get(route('auctions.show', $lot->auction->slug))->assertOk();
        $this->get(route('lots.show', $lot))->assertOk();
    }

    public function test_draft_auction_and_its_lots_are_hidden(): void
    {
        $lot = Lot::factory()->create();
        $lot->auction->forceFill(['status' => 'draft'])->save();

        $this->get(route('auctions.show', $lot->auction->slug))->assertNotFound();
        $this->get(route('lots.show', $lot))->assertNotFound();
    }

    public function test_lot_state_never_exposes_reserve_price(): void
    {
        $lot = Lot::factory()->create(['reserve_price' => 7_777_777]);

        $response = $this->getJson(route('lots.state', $lot))->assertOk();

        $this->assertStringNotContainsString('7777777', $response->getContent());
        $response->assertJsonPath('reserve_met', false)->assertJsonMissingPath('reserve_price');
    }

    public function test_guest_is_redirected_to_login_when_bidding(): void
    {
        $lot = Lot::factory()->create();

        $this->post(route('lots.bid', $lot), ['amount' => 500_000])->assertRedirect(route('login'));
    }

    public function test_verified_bidder_can_bid_over_http(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        $user = User::factory()->verified()->create();

        $this->actingAs($user)->from(route('lots.show', $lot))
            ->post(route('lots.bid', $lot), ['amount' => 500_000])
            ->assertRedirect(route('lots.show', $lot))
            ->assertSessionHas('success');

        $this->assertSame($user->id, $lot->fresh()->leader_id);
    }

    public function test_rejected_bid_shows_friendly_error(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000]);

        $this->actingAs(User::factory()->create()) // belum KYC
            ->post(route('lots.bid', $lot), ['amount' => 500_000])
            ->assertSessionHas('error');
    }

    public function test_bidder_cannot_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->verified()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_staff_can_manage_items_but_not_finance(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.items.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.invoices.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.bidders.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_without_two_factor_is_forced_to_enable_it(): void
    {
        $admin = User::factory()->admin()->create(['two_factor_confirmed_at' => null]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.security.two-factor'));
        $this->actingAs($admin)->get(route('admin.security.two-factor'))->assertOk();
    }

    public function test_admin_with_two_factor_can_open_every_admin_page(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $lot = Lot::factory()->create();

        foreach ([
            'admin.dashboard', 'admin.consignors.index', 'admin.consignors.create', 'admin.items.index', 'admin.items.create',
            'admin.categories.index', 'admin.auctions.index', 'admin.auctions.create', 'admin.bidders.index',
            'admin.invoices.index', 'admin.settlements.index', 'admin.audit-logs.index', 'admin.users.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.auctions.show', $lot->auction))->assertOk();
        $this->actingAs($admin)->get(route('admin.items.show', $lot->item))->assertOk();
        $this->actingAs($admin)->get(route('admin.consignors.show', $lot->item->consignor))->assertOk();
    }

    public function test_bidder_cannot_view_someone_elses_invoice(): void
    {
        $invoice = $this->soldInvoice();
        $other = User::factory()->verified()->create();

        $this->actingAs($invoice->user)->get(route('user.invoices.show', $invoice))->assertOk();
        $this->actingAs($other)->get(route('user.invoices.show', $invoice))->assertNotFound();
    }

    public function test_registration_cannot_escalate_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Penyusup', 'email' => 'x@contoh.test', 'phone' => '081234567890',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'terms' => '1',
            'role' => 'super_admin', 'kyc_status' => 'verified',
        ]);

        $user = User::where('email', 'x@contoh.test')->firstOrFail();
        $this->assertSame(Role::Bidder, $user->role);
        $this->assertFalse($user->isKycVerified());
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_blocked' => true]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    private function soldInvoice(): Invoice
    {
        $lot = Lot::factory()->create(['reserve_price' => 0]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 500_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        return $lot->fresh()->invoice;
    }
}
