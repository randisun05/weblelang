<?php

namespace Tests\Feature\Http;

use App\Enums\AuctionMethod;
use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Services\Auction\BidService;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function lot(AuctionMethod $method = AuctionMethod::Open, array $auction = []): Lot
    {
        $session = Auction::factory()->create(['method' => $method] + $auction);

        return Lot::factory()->for($session)->create(['starting_price' => 1_000_000, 'reserve_price' => 4_321_000]);
    }

    public function test_lot_page_renders_open_graph_and_product_json_ld_server_side(): void
    {
        $lot = $this->lot();
        $lot->item->update(['title' => 'Jam Tangan Seiko 5']);
        $lot->item->images()->create(['path' => 'items/1/foto.webp', 'sort_order' => 1]);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 1_000_000);

        $html = $this->get(route('lots.show', $lot))->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Lot '.$lot->lot_number.': Jam Tangan Seiko 5 — ', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('Tawaran saat ini Rp 1.000.000', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="http[^"]+/storage/items/1/foto.webp">#', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('lots.show', $lot).'">', $html);

        preg_match('#<script type="application/ld\+json">(.+?)</script>#', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('IDR', $data['offers']['priceCurrency']);
        $this->assertSame(1_000_000, $data['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);

        // Reserve price tidak pernah ikut keluar.
        $this->assertStringNotContainsString('4321000', $html);
        $this->assertStringNotContainsString('4.321.000', $html);
    }

    public function test_sealed_lot_meta_only_shows_starting_price(): void
    {
        $lot = $this->lot(AuctionMethod::Sealed);
        app(BidService::class)->place($lot, User::factory()->verified()->create(), 7_777_000);

        $html = $this->get(route('lots.show', $lot))->getContent();

        $this->assertStringContainsString('Harga awal Rp 1.000.000', $html);
        $this->assertStringNotContainsString('7777000', $html);
        $this->assertStringNotContainsString('7.777.000', $html);
    }

    public function test_json_ld_cannot_break_out_of_script_tag(): void
    {
        $lot = $this->lot();
        $lot->item->update(['title' => '</script><script>alert(1)</script>']);

        $html = $this->get(route('lots.show', $lot))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }

    public function test_auction_page_has_event_schema_and_default_image(): void
    {
        $lot = $this->lot(auction: ['title' => 'Lelang Elektronik Oktober']);

        $html = $this->get(route('auctions.show', $lot->auction->slug))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"Event"', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Lelang Elektronik Oktober">', $html);
    }

    public function test_private_pages_are_noindex(): void
    {
        $this->get(route('home'))->assertHeaderMissing('X-Robots-Tag');

        $this->actingAs(User::factory()->verified()->create())
            ->get(route('user.dashboard'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $public = $this->lot();
        $draft = $this->lot(auction: ['status' => AuctionStatus::Draft]);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString('<loc>'.route('lots.show', $public).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('auctions.show', $public->auction->slug).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('consign.create').'</loc>', $xml);
        $this->assertStringNotContainsString(route('lots.show', $draft), $xml);
        $this->assertStringNotContainsString($draft->auction->slug, $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertSee('Disallow: /admin', false)->assertSee('Sitemap: '.route('seo.sitemap'), false);
    }

    public function test_item_photos_are_watermarked(): void
    {
        Storage::fake('public');
        $canvas = imagecreatetruecolor(800, 600);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'wm').'.jpg';
        imagejpeg($canvas, $tmp);
        $file = new UploadedFile($tmp, 'putih.jpg', 'image/jpeg', null, true); // gambar putih polos
        $images = app(ImageService::class);

        config(['auction.watermark.enabled' => false]);
        $plain = $images->store($file, 'items/1', watermark: true);
        config(['auction.watermark.enabled' => true]);
        $marked = $images->store($file, 'items/1', watermark: true);

        $pixels = fn (string $path) => imagecreatefromstring(Storage::disk('public')->get($path));
        $count = function ($img) {
            $dark = 0;
            for ($x = 500; $x < 800; $x += 2) {
                for ($y = 540; $y < 600; $y += 2) {
                    $dark += (imagecolorat($img, $x, $y) & 0xFF) < 200 ? 1 : 0;
                }
            }

            return $dark;
        };

        $this->assertSame(0, $count($pixels($plain)));
        $this->assertGreaterThan(20, $count($pixels($marked)), 'Watermark tidak terlihat di pojok kanan bawah.');
    }
}
