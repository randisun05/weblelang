<?php

namespace Tests\Feature\Http;

use App\Enums\AuctionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\SettlementStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Consignor;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_consignment_flow_from_intake_to_settlement(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $category = Category::factory()->create(['attribute_schema' => [
            ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'required' => true],
        ]]);

        // 1. Staf mendaftarkan penitip & menerima barang (atribut wajib divalidasi).
        $this->actingAs($staff)->post(route('admin.consignors.store'), [
            'name' => 'Pak Penitip', 'phone' => '081111111111', 'email' => 'penitip@contoh.test',
            'bank_name' => 'BCA', 'bank_account' => '1234567890', 'bank_holder' => 'Pak Penitip', 'commission_rate' => 10,
        ])->assertRedirect();
        $consignor = Consignor::firstOrFail();
        $this->assertSame('1234567890', $consignor->bank_account);
        $this->assertStringNotContainsString('1234567890', (string) \DB::table('consignors')->value('bank_account'), 'Rekening harus terenkripsi di database.');

        $payload = [
            'consignor_id' => $consignor->id, 'category_id' => $category->id, 'title' => 'Kamera Antik',
            'condition' => 'bekas_baik', 'reserve_price' => 2_000_000,
            'images' => [UploadedFile::fake()->image('foto.jpg', 1200, 900)],
        ];
        $this->actingAs($staff)->post(route('admin.items.store'), $payload)->assertSessionHasErrors('specs.merk');

        $this->actingAs($staff)->post(route('admin.items.store'), $payload + ['specs' => ['merk' => 'Leica']])->assertRedirect();
        $item = Item::firstOrFail();
        $this->assertStringStartsWith('BRG-', $item->code);
        $this->assertStringEndsWith('.webp', $item->images()->first()->path);
        Storage::disk('public')->assertExists($item->images()->first()->path);

        // 2. Staf inspeksi; hanya admin yang boleh menyetujui.
        $this->actingAs($staff)->post(route('admin.items.transition', $item), ['status' => 'inspected', 'notes' => 'OK'])->assertSessionHas('success');
        $this->actingAs($staff)->post(route('admin.items.transition', $item), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.items.transition', $item), ['status' => 'sold'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.items.transition', $item), ['status' => 'approved'])->assertSessionHas('success');
        $this->assertSame(ItemStatus::Approved, $item->fresh()->status);

        // 3. Admin membuat sesi, menambah lot, menerbitkan.
        $this->actingAs($admin)->post(route('admin.auctions.store'), [
            'title' => 'Lelang Uji', 'starts_at' => now()->subMinute()->format('Y-m-d H:i'), 'ends_at' => now()->addHour()->format('Y-m-d H:i'),
            'deposit_amount' => 0, 'buyer_premium_rate' => 5, 'anti_snipe_minutes' => 3, 'extend_minutes' => 3, 'stagger_seconds' => 0,
        ])->assertRedirect();
        $auction = Auction::firstOrFail();

        $this->actingAs($admin)->post(route('admin.auctions.publish', $auction))->assertSessionHas('error'); // belum ada lot
        $this->actingAs($admin)->post(route('admin.auctions.lots.store', $auction), [
            'lots' => [['item_id' => $item->id, 'starting_price' => 1_000_000]],
        ])->assertSessionHas('success');
        $this->assertSame(ItemStatus::Listed, $item->fresh()->status);
        $this->actingAs($admin)->post(route('admin.auctions.publish', $auction))->assertSessionHas('success');
        $this->artisan('auctions:tick');
        $lot = $auction->lots()->first();
        $this->assertSame(LotStatus::Live, $lot->status);

        // 4. Peserta daftar KYC, admin verifikasi, peserta menawar.
        $bidder = User::factory()->create();
        $this->actingAs($bidder)->post(route('user.profile.kyc'), [
            'nik' => '3171234567890001', 'address' => 'Jakarta', 'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertSessionHas('success');
        $this->assertSame(KycStatus::Pending, $bidder->fresh()->kyc_status);
        Storage::disk('local')->assertExists($bidder->fresh()->ktp_path);

        $this->actingAs($admin)->get(route('admin.bidders.ktp', $bidder))->assertOk();
        $this->actingAs($bidder)->get(route('admin.bidders.ktp', $bidder))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.bidders.kyc', $bidder), ['decision' => 'verified'])->assertSessionHas('success');

        $this->actingAs($bidder->fresh())->post(route('lots.bid', $lot), ['amount' => 2_500_000])->assertSessionHas('success');

        // 5. Lot ditutup → invoice → bayar → settlement → serah terima.
        $this->travel(2)->hours();
        $this->artisan('auctions:tick');
        $invoice = $lot->fresh()->invoice;
        $this->assertSame(2_625_000, $invoice->total);
        $this->assertSame(AuctionStatus::Closed, $auction->fresh()->status);

        $this->actingAs($bidder)->post(route('user.invoices.proof', $invoice), ['proof' => UploadedFile::fake()->image('tf.jpg')])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.invoices.paid', $invoice), ['reference' => 'MUTASI-1'])->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $settlement = $invoice->fresh()->settlement;
        $this->assertSame(2_250_000, $settlement->net_amount);

        $this->actingAs($admin)->post(route('admin.invoices.deliver', $invoice))->assertSessionHas('success');
        $this->assertSame(ItemStatus::Delivered, $item->fresh()->status);

        $this->actingAs($admin)->post(route('admin.settlements.paid', $settlement), ['proof' => UploadedFile::fake()->image('bukti.jpg')])->assertSessionHas('success');
        $this->assertSame(SettlementStatus::Paid, $settlement->fresh()->status);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settlement.paid']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.verified']);
    }
}
