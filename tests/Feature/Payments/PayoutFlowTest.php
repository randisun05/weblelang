<?php

namespace Tests\Feature\Payments;

use App\Enums\AuctionStatus;
use App\Enums\DepositStatus;
use App\Enums\PayoutStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SettlementStatus;
use App\Models\AuctionRegistration;
use App\Models\Lot;
use App\Models\Payout;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\DepositSettledNotification;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PayoutService;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function pendingSettlement(): Settlement
    {
        $lot = Lot::factory()->create(['reserve_price' => 0, 'starting_price' => 1_000_000]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();
        app(InvoiceService::class)->markPaid($lot->fresh()->invoice, 'transfer');

        return Settlement::firstOrFail();
    }

    // ---- Payout ke penitip ------------------------------------------------

    public function test_admin_pays_consignor_via_simulator(): void
    {
        config(['payments.payout_gateway' => 'simulator']);
        $settlement = $this->pendingSettlement();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settlements.payout', $settlement))->assertSessionHas('success');

        $payout = Payout::firstOrFail();
        $this->assertSame(PayoutStatus::Completed, $payout->status);
        $this->assertSame($settlement->net_amount, $payout->amount);
        $this->assertSame($settlement->consignor->bank_account, $payout->account_number);
        $this->assertSame(SettlementStatus::Paid, $settlement->fresh()->status);

        // Tidak bisa ditransfer dua kali.
        $this->actingAs($admin)->post(route('admin.settlements.payout', $settlement))->assertSessionHas('error');
        $this->assertSame(1, Payout::count());

        $this->actingAs(User::factory()->staff()->create())->post(route('admin.settlements.payout', $settlement))->assertForbidden();
    }

    public function test_payout_requires_complete_bank_details(): void
    {
        config(['payments.payout_gateway' => 'simulator']);
        $settlement = $this->pendingSettlement();
        $settlement->consignor->update(['bank_name' => null]);

        $this->expectException(GatewayException::class);
        app(PayoutService::class)->send($settlement);
    }

    public function test_xendit_disbursement_lifecycle_with_retry_after_failure(): void
    {
        config(['payments.payout_gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd', 'payments.drivers.xendit.callback_token' => 'cb']);
        Http::fake(['api.xendit.co/disbursements' => Http::sequence()
            ->push(['id' => 'disb_1', 'status' => 'PENDING'])
            ->push(['id' => 'disb_2', 'status' => 'PENDING'])]);
        $settlement = $this->pendingSettlement();

        $first = app(PayoutService::class)->send($settlement);
        $this->assertSame(PayoutStatus::Processing, $first->status);
        Http::assertSent(fn ($req) => $req->hasHeader('X-IDEMPOTENCY-KEY', $first->reference) && $req['bank_code'] === 'BCA');

        $this->postJson(route('payouts.webhook', 'xendit'), ['id' => 'disb_1', 'external_id' => $first->reference, 'status' => 'FAILED', 'failure_code' => 'INVALID_DESTINATION', 'amount' => $first->amount], ['x-callback-token' => 'cb'])->assertOk();
        $this->assertSame(PayoutStatus::Failed, $first->fresh()->status);
        $this->assertSame('INVALID_DESTINATION', $first->fresh()->failure_reason);
        $this->assertSame(SettlementStatus::Pending, $settlement->fresh()->status);

        // Setelah gagal, admin boleh mencoba lagi.
        $second = app(PayoutService::class)->send($settlement->fresh());
        $this->postJson(route('payouts.webhook', 'xendit'), ['id' => 'disb_2', 'external_id' => $second->reference, 'status' => 'COMPLETED', 'amount' => $second->amount], ['x-callback-token' => 'bad'])->assertForbidden();
        $this->postJson(route('payouts.webhook', 'xendit'), ['id' => 'disb_2', 'external_id' => $second->reference, 'status' => 'COMPLETED', 'amount' => $second->amount], ['x-callback-token' => 'cb'])->assertOk();

        $this->assertSame(PayoutStatus::Completed, $second->fresh()->status);
        $this->assertSame(SettlementStatus::Paid, $settlement->fresh()->status);
    }

    public function test_midtrans_iris_payout_with_signed_notification(): void
    {
        config([
            'payments.payout_gateway' => 'midtrans',
            'payments.drivers.midtrans.iris_creator_key' => 'creator',
            'payments.drivers.midtrans.iris_approver_key' => 'approver',
            'payments.drivers.midtrans.iris_merchant_key' => 'merchant',
        ]);
        Http::fake([
            'app.sandbox.midtrans.com/iris/api/v1/payouts' => Http::response(['payouts' => [['status' => 'queued', 'reference_no' => 'iris-ref-1']]], 201),
            'app.sandbox.midtrans.com/iris/api/v1/payouts/approve' => Http::response(['status' => 'ok']),
        ]);
        $settlement = $this->pendingSettlement();

        $payout = app(PayoutService::class)->send($settlement);
        $this->assertSame('iris-ref-1', $payout->provider_ref);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/payouts/approve') && $req['reference_nos'] === ['iris-ref-1']);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/iris/api/v1/payouts') && $req['payouts'][0]['beneficiary_bank'] === 'bca');

        $body = json_encode(['reference_no' => 'iris-ref-1', 'amount' => $payout->amount.'.0', 'status' => 'completed']);
        $this->call('POST', route('payouts.webhook', 'midtrans'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_IRIS_SIGNATURE' => 'forged'], $body)->assertForbidden();
        $this->call('POST', route('payouts.webhook', 'midtrans'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_IRIS_SIGNATURE' => hash('sha512', $body.'merchant'),
        ], $body)->assertOk();

        $this->assertSame(PayoutStatus::Completed, $payout->fresh()->status);
        $this->assertSame(SettlementStatus::Paid, $settlement->fresh()->status);
    }

    public function test_manual_marking_is_blocked_while_gateway_transfer_is_in_flight(): void
    {
        config(['payments.payout_gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd', 'payments.drivers.xendit.callback_token' => 'cb']);
        Http::fake(['api.xendit.co/disbursements' => Http::response(['id' => 'disb_9', 'status' => 'PENDING'])]);
        $settlement = $this->pendingSettlement();
        $admin = User::factory()->admin()->create();
        $payout = app(PayoutService::class)->send($settlement);

        $this->actingAs($admin)->post(route('admin.settlements.paid', $settlement), ['proof' => UploadedFile::fake()->image('b.jpg')])
            ->assertSessionHas('error');
        $this->assertSame(SettlementStatus::Pending, $settlement->fresh()->status);

        $this->postJson(route('payouts.webhook', 'xendit'), ['id' => 'disb_9', 'external_id' => $payout->reference, 'status' => 'COMPLETED', 'amount' => $payout->amount], ['x-callback-token' => 'cb'])->assertOk();
        $this->assertSame(SettlementStatus::Paid, $settlement->fresh()->status);
    }

    public function test_gateway_rejection_marks_payout_failed(): void
    {
        config(['payments.payout_gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd']);
        Http::fake(['api.xendit.co/*' => Http::response(['message' => 'Insufficient balance'], 400)]);
        $settlement = $this->pendingSettlement();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settlements.payout', $settlement))->assertSessionHas('error');

        $this->assertSame(PayoutStatus::Failed, Payout::first()->status);
        $this->assertStringContainsString('Insufficient balance', Payout::first()->failure_reason);
    }

    // ---- Refund jaminan otomatis ------------------------------------------

    private function closedAuctionWithDeposits(): array
    {
        $lot = Lot::factory()->create(['reserve_price' => 0, 'starting_price' => 500_000]);
        $lot->auction->update(['deposit_amount' => 1_000_000]);
        $winner = User::factory()->verified()->create(['bank_name' => 'BNI', 'bank_account' => '1112223334', 'bank_holder' => 'Winner']);
        $loser = User::factory()->verified()->create(['bank_name' => 'BRI', 'bank_account' => '5556667778', 'bank_holder' => 'Loser']);
        $noBank = User::factory()->verified()->create();

        $regs = collect([$winner, $loser, $noBank])->mapWithKeys(function (User $u) use ($lot) {
            $r = AuctionRegistration::create(['auction_id' => $lot->auction_id, 'user_id' => $u->id]);
            $r->forceFill(['status' => RegistrationStatus::Approved, 'deposit_status' => DepositStatus::Held])->save();

            return [$u->id => $r];
        });

        app(BidService::class)->place($lot->fresh(), $loser, 500_000);
        app(BidService::class)->place($lot->fresh(), $winner, 550_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();
        $this->assertSame(AuctionStatus::Closed, $lot->auction->fresh()->status);

        return [$lot->fresh(), $winner, $loser, $noBank, $regs];
    }

    public function test_deposits_are_refunded_automatically_after_auction_closes(): void
    {
        config(['payments.payout_gateway' => 'simulator']);
        [$lot, $winner, $loser, $noBank, $regs] = $this->closedAuctionWithDeposits();

        $this->artisan('auctions:tick')->assertSuccessful();

        // Kalah + ada rekening → dikembalikan.
        $this->assertSame(DepositStatus::Refunded, $regs[$loser->id]->fresh()->deposit_status);
        $this->assertTrue($loser->notifications()->where('type', DepositSettledNotification::class)->exists());
        $this->assertSame('5556667778', Payout::whereMorphedTo('payable', $regs[$loser->id])->first()->account_number);
        // Belum isi rekening → menunggu.
        $this->assertSame(DepositStatus::Held, $regs[$noBank->id]->fresh()->deposit_status);
        // Pemenang belum bayar invoice → jaminan ditahan.
        $this->assertSame(DepositStatus::Held, $regs[$winner->id]->fresh()->deposit_status);

        // Pemenang melunasi → jaminan ikut dikembalikan pada tick berikutnya.
        app(InvoiceService::class)->markPaid($lot->invoice, 'transfer');
        $this->artisan('auctions:tick');
        $this->assertSame(DepositStatus::Refunded, $regs[$winner->id]->fresh()->deposit_status);
        $this->assertSame(2, Payout::count());
    }

    public function test_failed_refund_is_not_retried_automatically(): void
    {
        config(['payments.payout_gateway' => 'xendit', 'payments.drivers.xendit.secret_key' => 'xnd']);
        Http::fake(['api.xendit.co/*' => Http::response(['message' => 'error'], 500)]);
        [, , $loser, , $regs] = $this->closedAuctionWithDeposits();

        $this->artisan('auctions:tick');
        $this->artisan('auctions:tick');

        $this->assertSame(1, Payout::whereMorphedTo('payable', $regs[$loser->id])->count());
        $this->assertSame(DepositStatus::Held, $regs[$loser->id]->fresh()->deposit_status);
    }

    public function test_admin_can_refund_deposit_via_gateway_and_bidder_manages_bank_account(): void
    {
        config(['payments.payout_gateway' => 'simulator']);
        $lot = Lot::factory()->create();
        $lot->auction->update(['deposit_amount' => 750_000]);
        $bidder = User::factory()->verified()->create();
        $registration = AuctionRegistration::create(['auction_id' => $lot->auction_id, 'user_id' => $bidder->id]);
        $registration->forceFill(['status' => RegistrationStatus::Approved, 'deposit_status' => DepositStatus::Held])->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.registrations.deposit', $registration), ['decision' => 'refunded', 'via_gateway' => true])->assertSessionHas('error');

        $this->actingAs($bidder)->put(route('user.profile.bank'), ['bank_name' => 'XYZ', 'bank_account' => '123', 'bank_holder' => ''])
            ->assertSessionHasErrors(['bank_name', 'bank_account', 'bank_holder']);
        $this->actingAs($bidder)->put(route('user.profile.bank'), ['bank_name' => 'MANDIRI', 'bank_account' => '1234567890', 'bank_holder' => 'Peserta'])
            ->assertSessionHas('success');
        $this->assertNotSame('1234567890', \DB::table('users')->where('id', $bidder->id)->value('bank_account'));

        $this->actingAs($admin)->post(route('admin.registrations.deposit', $registration), ['decision' => 'refunded', 'via_gateway' => true])->assertSessionHas('success');
        $this->assertSame(DepositStatus::Refunded, $registration->fresh()->deposit_status);
        $this->assertSame(750_000, Payout::first()->amount);
    }

    public function test_transactions_page_for_admin(): void
    {
        config(['payments.payout_gateway' => 'simulator']);
        app(PayoutService::class)->send($this->pendingSettlement());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.transactions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.transactions.index', ['tab' => 'payouts']))->assertOk()->assertDontSee('1234567890');
    }
}
