<?php

namespace Tests\Feature\Http;

use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_render_with_operator_identity(): void
    {
        config(['legal.operator.legal_entity' => 'PT Lelang Jujur Sejahtera']);

        $this->get(route('legal.terms'))->assertOk()->assertSee('PT Lelang Jujur Sejahtera');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('PT Lelang Jujur Sejahtera');
    }

    public function test_registration_records_consent_version(): void
    {
        $this->post(route('register'), [
            'name' => 'Baru', 'email' => 'baru@contoh.test', 'phone' => '081234567890',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'terms' => '1',
        ]);

        $user = User::where('email', 'baru@contoh.test')->firstOrFail();
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertFalse($user->needsTermsAcceptance());
    }

    public function test_users_must_reaccept_when_terms_change(): void
    {
        $user = User::factory()->verified()->create();
        config(['legal.terms_version' => '2099-01-01']);

        $this->assertTrue($user->fresh()->needsTermsAcceptance());
        $this->actingAs($user)->post(route('legal.accept'), [])->assertSessionHasErrors('accept');
        $this->actingAs($user)->post(route('legal.accept'), ['accept' => '1'])->assertSessionHas('success');

        $this->assertSame('2099-01-01', $user->fresh()->terms_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.terms_accepted', 'subject_id' => $user->id]);
        // Petugas tidak diminta (tidak ikut lelang).
        $this->assertFalse(User::factory()->admin()->create(['terms_version' => null])->needsTermsAcceptance());
    }

    public function test_user_can_download_only_their_own_data(): void
    {
        $user = User::factory()->verified()->create(['bank_name' => 'BCA', 'bank_account' => '1234567890', 'bank_holder' => 'Saya']);
        $other = User::factory()->verified()->create(['email' => 'orang.lain@contoh.test']);
        $lot = Lot::factory()->create(['starting_price' => 500_000]);
        app(BidService::class)->place($lot, $user, 500_000);
        app(BidService::class)->place($lot->fresh(), $other, 525_000);

        $this->get(route('user.profile.export'))->assertRedirect(route('login'));

        $response = $this->actingAs($user)->get(route('user.profile.export'))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $response->assertJsonPath('profil.email', $user->email)
            ->assertJsonPath('profil.rekening.nomor', '1234567890')
            ->assertJsonCount(1, 'penawaran');
        $this->assertStringNotContainsString('orang.lain@contoh.test', $response->getContent());
    }
}
