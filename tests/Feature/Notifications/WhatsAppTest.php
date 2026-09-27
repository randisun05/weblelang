<?php

namespace Tests\Feature\Notifications;

use App\Models\ConsignmentRequest;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\OutbidNotification;
use App\Services\Auction\BidService;
use App\Services\Auction\LotCloser;
use App\Services\InvoiceService;
use App\Services\SettlementService;
use App\WhatsApp\WhatsAppException;
use App\WhatsApp\WhatsAppManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    /** Respons tiruan Fonnte; bisa diganti per tes. */
    private array $fonnte = ['status' => true, 'id' => ['80367170']];

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => 'tok-123']);
        Http::fake(['api.fonnte.com/*' => fn () => Http::response($this->fonnte)]);
    }

    /** @return array<int, array{target: string, message: string}> */
    private function sent(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    private function wonInvoice(User $winner): Invoice
    {
        $lot = Lot::factory()->create(['reserve_price' => 0, 'starting_price' => 500_000]);
        $lot->item->consignor->update(['phone' => '0813-1111-2222', 'email' => null]);
        app(BidService::class)->place($lot, $winner, 1_000_000);
        $this->travel(2)->days();
        app(LotCloser::class)->closeDue();

        return $lot->fresh()->invoice;
    }

    public function test_phone_numbers_are_normalized(): void
    {
        $wa = app(WhatsAppManager::class);

        $this->assertSame('6281234567890', $wa->normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', $wa->normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $wa->normalize('81234567890'));
        $this->assertNull($wa->normalize('12345'));
        $this->assertNull($wa->normalize(null));
    }

    public function test_fonnte_driver_sends_form_with_token(): void
    {
        $id = app(WhatsAppManager::class)->send('081234567890', 'Halo');

        $this->assertSame('80367170', $id);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fonnte.com/send'
            && $r->hasHeader('Authorization', 'tok-123')
            && $r['target'] === '6281234567890' && $r['message'] === 'Halo');
    }

    public function test_provider_rejection_throws_so_the_queue_retries(): void
    {
        $this->fonnte = ['status' => false, 'reason' => 'device disconnected'];

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('device disconnected');

        app(WhatsAppManager::class)->send('081234567890', 'Halo');
    }

    public function test_wablas_driver_uses_account_server_and_secret(): void
    {
        config(['whatsapp.driver' => 'wablas', 'whatsapp.wablas.token' => 'tk', 'whatsapp.wablas.secret_key' => 'sk',
            'whatsapp.wablas.base_url' => 'https://tegal.wablas.com']);
        Http::fake(['tegal.wablas.com/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'abc']]]])]);

        $this->assertSame('abc', app(WhatsAppManager::class)->send('081234567890', 'Halo'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://tegal.wablas.com/api/send-message'
            && $r->hasHeader('Authorization', 'tk.sk') && $r['phone'] === '6281234567890');
    }

    public function test_outbid_bidder_gets_whatsapp_unless_opted_out(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 1_000_000]);
        $first = User::factory()->verified()->create(['phone' => '081200000001']);
        $second = User::factory()->verified()->create(['phone' => '081200000002']);

        app(BidService::class)->place($lot, $first, 1_000_000);
        app(BidService::class)->place($lot->fresh(), $second, 1_100_000);

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('6281200000001', $sent[0]['target']);
        $this->assertStringContainsString('*Penawaran Anda terlampaui*', $sent[0]['message']);
        $this->assertStringContainsString(route('lots.show', $lot), $sent[0]['message']);

        // Pengguna mematikan notifikasi WA di profil → tidak dikirim (email & lonceng tetap).
        $second->update(['whatsapp_notifications' => false]);
        app(BidService::class)->place($lot->fresh(), $first, 1_300_000);
        $this->assertCount(1, $this->sent());
        $this->assertSame(1, $second->notifications()->count());
    }

    public function test_whatsapp_channel_is_skipped_when_disabled(): void
    {
        config(['whatsapp.driver' => null]);
        $user = User::factory()->verified()->create(['phone' => '081200000001']);

        $this->assertNotContains('whatsapp', (new OutbidNotification(Lot::factory()->create(), 1))->via($user));
        Http::assertNothingSent();
    }

    public function test_consignor_is_told_when_item_sold_and_when_paid(): void
    {
        $invoice = $this->wonInvoice(User::factory()->verified()->create(['phone' => '081200000009']));

        app(InvoiceService::class)->markPaid($invoice, 'transfer');
        $settlement = $invoice->fresh()->settlement;

        $toConsignor = fn () => collect($this->sent())->where('target', '6281311112222')->values();
        $this->assertCount(1, $toConsignor());
        $this->assertStringContainsString('Barang titipan Anda terjual', $toConsignor()[0]['message']);
        $this->assertStringContainsString('portal-penitip', $toConsignor()[0]['message']);

        app(SettlementService::class)->markPaid($settlement, null);
        $this->assertCount(2, $toConsignor());
        $this->assertStringContainsString('sudah kami transfer', $toConsignor()[1]['message']);
    }

    public function test_consignment_request_submitter_gets_whatsapp(): void
    {
        Storage::fake('local');

        $this->post(route('consign.store'), [
            'name' => 'Sari', 'phone' => '081298765432', 'email' => 'sari@contoh.test', 'city' => 'Bandung',
            'title' => 'Kamera Analog', 'description' => 'Kondisi mulus, shutter normal, lengkap dengan lensa.',
            'condition' => 'bekas_baik', 'handover' => 'antar', 'consent' => '1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();

        $request = ConsignmentRequest::firstOrFail();
        $this->assertSame('6281298765432', $this->sent()[0]['target']);
        $this->assertStringContainsString($request->code, $this->sent()[0]['message']);
    }

    public function test_profile_toggle_and_test_command(): void
    {
        $user = User::factory()->verified()->create(['phone' => '081200000001']);

        $this->actingAs($user)->put(route('user.profile.update'), [
            'name' => $user->name, 'phone' => '081200000001', 'whatsapp_notifications' => false,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->whatsapp_notifications);
        $this->assertNull($user->fresh()->routeNotificationFor('whatsapp'));

        $this->artisan('whatsapp:test', ['phone' => '081234567890'])->assertSuccessful();
        $this->artisan('whatsapp:test', ['phone' => 'abc'])->assertFailed();
    }
}
