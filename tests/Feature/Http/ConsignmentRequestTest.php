<?php

namespace Tests\Feature\Http;

use App\Enums\ConsignmentRequestStatus;
use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ConsignmentRequest;
use App\Models\Consignor;
use App\Models\User;
use App\Notifications\ConsignmentRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConsignmentRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'name' => 'Sari Penitip', 'phone' => '081298765432', 'email' => 'sari@contoh.test', 'city' => 'Bandung',
            'title' => 'Kamera Analog Canon AE-1', 'description' => 'Kondisi mulus, shutter normal, lengkap dengan lensa 50mm.',
            'condition' => 'bekas_baik', 'expected_price' => 2500000, 'handover' => 'antar',
            'photos' => [UploadedFile::fake()->image('depan.jpg', 800, 600), UploadedFile::fake()->image('belakang.png', 800, 600)],
            'consent' => '1',
        ];
    }

    private function submit(array $extra = []): ConsignmentRequest
    {
        $this->post(route('consign.store'), $this->payload($extra))->assertSessionHasNoErrors();

        return ConsignmentRequest::latest('id')->firstOrFail();
    }

    public function test_form_is_public(): void
    {
        $this->get(route('consign.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Consign/Create')->has('handovers'));
    }

    public function test_guest_can_submit_and_photos_stay_private(): void
    {
        $response = $this->post(route('consign.store'), $this->payload());

        $request = ConsignmentRequest::firstOrFail();
        $response->assertRedirect();
        $this->assertStringContainsString('/titip-barang/status/'.$request->code, $response->headers->get('Location'));
        $this->assertStringStartsWith('TTP-', $request->code);
        $this->assertSame(ConsignmentRequestStatus::New, $request->status);
        $this->assertCount(2, $request->photos);
        foreach ($request->photos as $path) {
            Storage::disk('local')->assertExists($path);
            $this->assertStringEndsWith('.webp', $path);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());

        Notification::assertSentOnDemand(ConsignmentRequestNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'sari@contoh.test');
    }

    public function test_validation_requires_photos_consent_and_valid_phone(): void
    {
        $this->post(route('consign.store'), $this->payload(['photos' => [], 'consent' => null, 'phone' => '12345']))
            ->assertSessionHasErrors(['photos', 'consent', 'phone']);

        $this->assertSame(0, ConsignmentRequest::count());
    }

    public function test_submission_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('consign.store'), $this->payload())->assertRedirect();
        }

        $this->post(route('consign.store'), $this->payload())->assertTooManyRequests();
        $this->assertSame(5, ConsignmentRequest::count());
    }

    public function test_turnstile_protects_the_form(): void
    {
        config(['services.turnstile.secret_key' => 'secret', 'services.turnstile.site_key' => 'site']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->post(route('consign.store'), $this->payload(['cf-turnstile-response' => 'bot']))->assertSessionHasErrors('captcha');
        $this->assertSame(0, ConsignmentRequest::count());
    }

    public function test_status_page_requires_valid_signature(): void
    {
        $request = $this->submit();

        $this->get($request->statusUrl())->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Consign/Status')->where('request.code', $request->code));

        $this->get(route('consign.status', $request->code))->assertForbidden();
        $this->get(URL::temporarySignedRoute('consign.status', now()->subMinute(), $request->code))->assertForbidden();
    }

    public function test_bidders_cannot_access_admin_pages(): void
    {
        $request = $this->submit();
        $bidder = User::factory()->verified()->create();

        $this->actingAs($bidder)->get(route('admin.consign-requests.index'))->assertForbidden();
        $this->actingAs($bidder)->get(route('admin.consign-requests.photo', [$request, 0]))->assertForbidden();
    }

    public function test_staff_can_review_and_view_photos(): void
    {
        $request = $this->submit();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.consign-requests.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('requests.data', 1));
        $this->actingAs($staff)->get(route('admin.consign-requests.show', $request))->assertOk();
        $this->actingAs($staff)->get(route('admin.consign-requests.photo', [$request, 1]))->assertOk();
        $this->actingAs($staff)->get(route('admin.consign-requests.photo', [$request, 9]))->assertNotFound();

        $this->actingAs($staff)->post(route('admin.consign-requests.reviewing', $request))->assertRedirect();
        $this->assertSame(ConsignmentRequestStatus::Reviewing, $request->fresh()->status);
    }

    public function test_accept_creates_consignor_item_and_public_images(): void
    {
        $request = $this->submit();
        $category = Category::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.consign-requests.accept', $request), [
            'category_id' => $category->id, 'commission_rate' => 12.5, 'storage_location' => 'Rak A1',
        ])->assertRedirect()->assertSessionHas('success');

        $request->refresh();
        $this->assertSame(ConsignmentRequestStatus::Accepted, $request->status);
        $consignor = $request->consignor;
        $item = $request->item;

        $this->assertSame('sari@contoh.test', $consignor->email);
        $this->assertEquals(12.5, $consignor->commission_rate);
        $this->assertSame($consignor->id, $item->consignor_id);
        $this->assertSame(ItemStatus::Received, $item->status);
        $this->assertSame(2500000, $item->reserve_price);
        $this->assertCount(2, $item->images);
        $item->images->each(fn ($image) => Storage::disk('public')->assertExists($image->path));

        Notification::assertSentOnDemandTimes(ConsignmentRequestNotification::class, 2);

        // Tidak bisa diproses dua kali.
        $this->actingAs($staff)->post(route('admin.consign-requests.reject', $request), ['reason' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame(1, Consignor::count());
    }

    public function test_accept_can_reuse_existing_consignor(): void
    {
        $existing = Consignor::factory()->create(['phone' => '+62 812-9876-5432', 'email' => 'lama@contoh.test']);
        $request = $this->submit();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.consign-requests.show', $request))
            ->assertInertia(fn ($page) => $page->where('matches.0.id', $existing->id));

        $this->actingAs($staff)->post(route('admin.consign-requests.accept', $request), [
            'category_id' => Category::factory()->create()->id, 'consignor_id' => $existing->id, 'reserve_price' => 3000000,
        ])->assertRedirect();

        $this->assertSame(1, Consignor::count());
        $this->assertSame($existing->id, $request->fresh()->item->consignor_id);
        $this->assertSame(3000000, $request->fresh()->item->reserve_price);
    }

    public function test_reject_notifies_submitter_and_shows_reason(): void
    {
        $request = $this->submit();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('admin.consign-requests.reject', $request), ['reason' => 'Barang tiruan.'])
            ->assertSessionHas('success');

        $this->assertSame(ConsignmentRequestStatus::Rejected, $request->fresh()->status);
        Notification::assertSentOnDemandTimes(ConsignmentRequestNotification::class, 2);

        auth()->logout();
        $this->get($request->statusUrl())->assertInertia(fn ($page) => $page->where('request.reject_reason', 'Barang tiruan.'));
    }
}
