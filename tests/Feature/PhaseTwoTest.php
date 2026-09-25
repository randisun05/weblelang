<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\KycStatus;
use App\Enums\RegistrationStatus;
use App\Models\AuctionRegistration;
use App\Models\Consignor;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\InvoiceStatusNotification;
use App\Notifications\KycReviewedNotification;
use App\Notifications\LotEndingSoonNotification;
use App\Notifications\LotWonNotification;
use App\Notifications\OutbidNotification;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    private function wonInvoice(?User $winner = null, int $amount = 1_000_000): Invoice
    {
        $lot = Lot::factory()->create(['reserve_price' => 0, 'starting_price' => 500_000]);
        app(BidService::class)->place($lot, $winner ?? User::factory()->verified()->create(), $amount);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        return $lot->fresh()->invoice;
    }

    // ---- Notifikasi ------------------------------------------------------

    public function test_previous_leader_is_notified_when_outbid(): void
    {
        Notification::fake();
        $lot = Lot::factory()->create(['starting_price' => 1_000_000]);
        [$a, $b] = User::factory()->verified()->count(2)->create();

        app(BidService::class)->place($lot, $a, 1_000_000);
        Notification::assertNothingSent();

        app(BidService::class)->place($lot, $b, 1_050_000);
        Notification::assertSentTo($a, OutbidNotification::class, fn ($n) => $n->price === 1_050_000);
        Notification::assertNotSentTo($b, OutbidNotification::class);
    }

    public function test_winner_is_notified_and_notification_is_stored_in_app(): void
    {
        $winner = User::factory()->verified()->create();
        $invoice = $this->wonInvoice($winner);

        $stored = $winner->notifications()->where('type', LotWonNotification::class)->first();
        $this->assertNotNull($stored);
        $this->assertSame(route('user.invoices.show', $invoice), $stored->data['url']);

        $this->actingAs($winner)->get(route('user.notifications.index'))->assertOk();
        $this->actingAs($winner)->get(route('user.notifications.open', $stored->id))->assertRedirect($stored->data['url']);
        $this->assertNotNull($stored->fresh()->read_at);
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $winner = User::factory()->verified()->create();
        $this->wonInvoice($winner);
        $id = $winner->notifications()->value('id');

        $this->actingAs(User::factory()->create())->get(route('user.notifications.open', $id))->assertNotFound();
    }

    public function test_ending_soon_reminder_goes_to_watchers_and_bidders_once(): void
    {
        Notification::fake();
        $lot = Lot::factory()->create(['ends_at' => now()->addMinutes(10), 'starting_price' => 500_000]);
        $lot->auction->update(['anti_snipe_minutes' => 0]);
        $watcher = User::factory()->create();
        $watcher->watchlist()->attach($lot->id);
        $bidder = User::factory()->verified()->create();
        app(BidService::class)->place($lot->fresh(), $bidder, 500_000);
        $stranger = User::factory()->create();

        $closer = app(LotCloser::class);
        $this->assertSame(1, $closer->notifyEndingSoon());
        $this->assertSame(0, $closer->notifyEndingSoon());

        Notification::assertSentToTimes($watcher, LotEndingSoonNotification::class, 1);
        Notification::assertSentToTimes($bidder, LotEndingSoonNotification::class, 1);
        Notification::assertNotSentTo($stranger, LotEndingSoonNotification::class);
    }

    public function test_kyc_decision_notifies_bidder(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $bidder = User::factory()->create();
        $bidder->forceFill(['kyc_status' => KycStatus::Pending])->save();

        $this->actingAs($admin)->post(route('admin.bidders.kyc', $bidder), ['decision' => 'rejected', 'note' => 'Foto buram']);

        Notification::assertSentTo($bidder, KycReviewedNotification::class);
    }

    // ---- Invoice kedaluwarsa & jaminan -----------------------------------

    public function test_overdue_invoice_is_cancelled_automatically_and_deposit_forfeited(): void
    {
        $winner = User::factory()->verified()->create();
        $invoice = $this->wonInvoice($winner);
        $registration = AuctionRegistration::create(['auction_id' => $invoice->lot->auction_id, 'user_id' => $winner->id]);
        $registration->forceFill(['status' => RegistrationStatus::Approved, 'deposit_status' => DepositStatus::Held])->save();

        $this->travel(config('auction.invoice_due_hours') + 1)->hours();
        $this->artisan('auctions:tick')->assertSuccessful();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertStringContainsString('jatuh tempo', $invoice->cancel_reason);
        $this->assertSame(ItemStatus::Approved, $invoice->lot->item->status);
        $this->assertSame(DepositStatus::Forfeited, $registration->fresh()->deposit_status);
        $this->assertTrue($winner->notifications()->where('type', InvoiceStatusNotification::class)->exists());
    }

    public function test_invoice_not_yet_due_is_left_alone(): void
    {
        $invoice = $this->wonInvoice();

        $this->artisan('auctions:tick');

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_admin_refunds_held_deposit_once(): void
    {
        $admin = User::factory()->admin()->create();
        $lot = Lot::factory()->create();
        $lot->auction->update(['deposit_amount' => 1_000_000]);
        $bidder = User::factory()->verified()->create();
        $registration = AuctionRegistration::create(['auction_id' => $lot->auction_id, 'user_id' => $bidder->id]);

        $this->actingAs($admin)->post(route('admin.registrations.decide', $registration), ['decision' => 'approved']);
        $this->assertSame(DepositStatus::Held, $registration->fresh()->deposit_status);

        $this->actingAs($admin)->post(route('admin.registrations.deposit', $registration), ['decision' => 'refunded'])->assertSessionHas('success');
        $this->assertSame(DepositStatus::Refunded, $registration->fresh()->deposit_status);

        $this->actingAs($admin)->post(route('admin.registrations.deposit', $registration), ['decision' => 'forfeited', 'note' => 'x'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.registrations.decide', $registration), ['decision' => 'rejected'])->assertSessionHas('error');
        $this->assertSame(DepositStatus::Refunded, $registration->fresh()->deposit_status);
    }

    // ---- Dokumen & laporan -----------------------------------------------

    public function test_invoice_pdf_for_owner_and_admin_only(): void
    {
        $invoice = $this->wonInvoice();
        $admin = User::factory()->admin()->create();

        $this->actingAs($invoice->user)->get(route('user.invoices.pdf', $invoice))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->verified()->create())->get(route('user.invoices.pdf', $invoice))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.invoices.pdf', $invoice))->assertOk();

        // Berita acara hanya setelah lunas.
        $this->actingAs($admin)->get(route('admin.invoices.handover', $invoice))->assertStatus(422);
        $this->actingAs($admin)->post(route('admin.invoices.paid', $invoice));
        $this->actingAs($admin)->get(route('admin.invoices.handover', $invoice))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_page_and_excel_exports(): void
    {
        $invoice = $this->wonInvoice();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.invoices.paid', $invoice));

        $range = ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()];
        $this->actingAs($admin)->get(route('admin.reports.index', $range))->assertOk();

        $sales = $this->actingAs($admin)->get(route('admin.reports.sales', $range))->assertOk();
        $this->assertStringContainsString('.xlsx', $sales->headers->get('content-disposition'));
        $this->actingAs($admin)->get(route('admin.reports.settlements', $range))->assertOk();

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.reports.sales'))->assertForbidden();
    }

    // ---- Portal penitip --------------------------------------------------

    public function test_consignor_portal_requires_valid_signature_and_can_be_revoked(): void
    {
        $lot = Lot::factory()->create();
        $lot->item->update(['reserve_price' => 4_321_000]);
        $consignor = $lot->item->consignor;
        $url = $consignor->portalUrl();

        $this->get($url)->assertOk()->assertSee('4321000');

        // URL diubah (mis. id penitip lain) → tanda tangan tidak valid.
        $other = Consignor::factory()->create();
        $this->get(str_replace("/portal-penitip/{$consignor->id}", "/portal-penitip/{$other->id}", $url))->assertForbidden();
        $this->get(route('consignor.portal', $consignor))->assertForbidden();

        // Cabut → link lama mati, link baru jalan.
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.consignors.reset-portal', $consignor));
        $this->get($url)->assertForbidden();
        $this->get($consignor->fresh()->portalUrl())->assertOk();

        // Kedaluwarsa.
        $this->travel(config('auction.consignor_portal_days') + 1)->days();
        $this->get($consignor->fresh()->portalUrl())->assertOk();
        $this->get($url)->assertForbidden();
    }

    public function test_consignor_portal_does_not_leak_full_bank_account(): void
    {
        $consignor = Consignor::factory()->create(['bank_account' => '9876543210']);

        $this->get($consignor->portalUrl())->assertOk()->assertDontSee('9876543210')->assertSee('******3210');
    }
}
