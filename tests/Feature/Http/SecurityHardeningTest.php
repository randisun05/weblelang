<?php

namespace Tests\Feature\Http;

use App\Models\FraudFlag;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidException;
use App\Services\Auction\BidService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $extra = []): array
    {
        return [
            'name' => 'Calon Peserta', 'email' => 'calon@contoh.test', 'phone' => '081234567890',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'terms' => '1',
        ] + $extra;
    }

    // ---- Turnstile -------------------------------------------------------

    public function test_turnstile_is_skipped_when_not_configured(): void
    {
        config(['services.turnstile.secret_key' => null]);

        $this->post(route('register'), $this->registration());

        $this->assertDatabaseHas('users', ['email' => 'calon@contoh.test']);
    }

    public function test_turnstile_blocks_bots_on_register_and_login(): void
    {
        config(['services.turnstile.secret_key' => 'secret', 'services.turnstile.site_key' => 'site']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true])
            ->push(['success' => false])]);

        $this->post(route('register'), $this->registration(['cf-turnstile-response' => 'bot']))->assertSessionHasErrors('captcha');
        $this->assertDatabaseMissing('users', ['email' => 'calon@contoh.test']);

        $this->post(route('register'), $this->registration(['cf-turnstile-response' => 'human']));
        $this->assertDatabaseHas('users', ['email' => 'calon@contoh.test']);
        auth()->logout();

        $this->post(route('login'), ['email' => 'calon@contoh.test', 'password' => 'rahasia123', 'cf-turnstile-response' => 'bot'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();

        // Tanpa token sama sekali → ditolak tanpa memanggil Cloudflare.
        $this->post(route('login'), ['email' => 'calon@contoh.test', 'password' => 'rahasia123'])->assertSessionHasErrors('captcha');
        Http::assertSentCount(3);
    }

    // ---- Verifikasi email ------------------------------------------------

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), $this->registration());

        Notification::assertSentTo(User::where('email', 'calon@contoh.test')->first(), VerifyEmail::class);
    }

    public function test_unverified_email_cannot_bid_or_submit_kyc(): void
    {
        $user = User::factory()->verified()->unverified()->create();
        $lot = Lot::factory()->create(['starting_price' => 500_000]);

        try {
            app(BidService::class)->place($lot, $user, 500_000);
            $this->fail('Email belum terverifikasi seharusnya ditolak.');
        } catch (BidException $e) {
            $this->assertStringContainsString('email', $e->getMessage());
        }

        $this->actingAs($user)->post(route('user.profile.kyc'), [])->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    // ---- Deteksi shill bidding ------------------------------------------

    public function test_accounts_bidding_from_same_device_are_flagged_once(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        [$a, $b, $c] = User::factory()->verified()->count(3)->create();
        $device = str_repeat('D', 32);

        $this->actingAs($a)->withCookie('wl_did', $device)->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post(route('lots.bid', $lot), ['amount' => 500_000]);
        $this->actingAs($c)->withCookie('wl_did', str_repeat('C', 32))->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->post(route('lots.bid', $lot), ['amount' => 525_000]);
        $this->actingAs($b)->withCookie('wl_did', $device)->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->post(route('lots.bid', $lot), ['amount' => 550_000]);
        $this->actingAs($a)->withCookie('wl_did', $device)->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post(route('lots.bid', $lot), ['amount' => 575_000]);

        $this->assertSame(4, $lot->fresh()->bids_count);
        $flags = FraudFlag::all();
        $this->assertCount(1, $flags, 'Satu flag perangkat, tanpa duplikat.');
        $this->assertSame('shared_device', $flags[0]->rule);
        $this->assertSame('high', $flags[0]->severity);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $flags[0]->user_ids);
    }

    public function test_shared_ip_and_consignor_phone_match_are_flagged(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        $lot->item->consignor->update(['phone' => '0812-1111-2222']);
        $a = User::factory()->verified()->create();
        $shill = User::factory()->verified()->create(['phone' => '+6281211112222']);

        $this->actingAs($a)->withCookie('wl_did', str_repeat('A', 32))->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post(route('lots.bid', $lot), ['amount' => 500_000]);
        $this->actingAs($shill)->withCookie('wl_did', str_repeat('B', 32))->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post(route('lots.bid', $lot), ['amount' => 525_000]);

        $this->assertEqualsCanonicalizing(['shared_ip', 'consignor_match'], FraudFlag::pluck('rule')->all());
        $this->assertSame([$shill->id], FraudFlag::where('rule', 'consignor_match')->first()->user_ids);
    }

    public function test_admin_confirms_flag_and_blocks_accounts(): void
    {
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        [$a, $b] = User::factory()->verified()->count(2)->create();
        foreach ([[$a, 500_000], [$b, 525_000]] as [$user, $amount]) {
            $this->actingAs($user)->withCookie('wl_did', str_repeat('Z', 32))->post(route('lots.bid', $lot), ['amount' => $amount]);
        }
        $flag = FraudFlag::where('rule', 'shared_device')->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.fraud.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.fraud.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.fraud.review', $flag), ['decision' => 'confirmed', 'block_users' => true])->assertSessionHas('success');

        $this->assertSame('confirmed', $flag->fresh()->status);
        $this->assertTrue($a->fresh()->is_blocked);
        $this->assertTrue($b->fresh()->is_blocked);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fraud.confirmed']);
    }

    public function test_device_cookie_is_issued_to_visitors(): void
    {
        $this->get('/')->assertCookie('wl_did');
    }
}
