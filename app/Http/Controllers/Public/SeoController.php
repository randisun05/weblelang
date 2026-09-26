<?php

namespace App\Http\Controllers\Public;

use App\Enums\AuctionStatus;
use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Lot;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml & robots.txt dinamis. */
class SeoController extends Controller
{
    /** Batas aman di bawah maksimum 50.000 URL per sitemap. */
    private const MAX_LOTS = 20000;

    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap', now()->addHour(), function () {
            $urls = collect([
                [route('home'), now(), 'daily', '1.0'],
                [route('auctions.index'), now(), 'daily', '0.9'],
                [route('consign.create'), null, 'monthly', '0.7'],
                [route('how-it-works'), null, 'monthly', '0.5'],
                [route('legal.terms'), null, 'yearly', '0.2'],
                [route('legal.privacy'), null, 'yearly', '0.2'],
            ]);

            Auction::where('status', '!=', AuctionStatus::Draft)->latest('starts_at')->get(['slug', 'status', 'updated_at'])
                ->each(fn (Auction $a) => $urls->push([
                    route('auctions.show', $a->slug), $a->updated_at, $a->status === AuctionStatus::Closed ? 'yearly' : 'hourly', '0.8',
                ]));

            Lot::without('auction')->publiclyVisible()
                ->where('status', '!=', LotStatus::Cancelled)
                ->latest('id')->limit(self::MAX_LOTS)->get(['id', 'status', 'updated_at'])
                ->each(fn (Lot $l) => $urls->push([
                    route('lots.show', $l->id), $l->updated_at,
                    in_array($l->status, [LotStatus::Live, LotStatus::Scheduled], true) ? 'hourly' : 'yearly', '0.6',
                ]));

            $body = $urls->map(fn ($u) => '<url><loc>'.e($u[0]).'</loc>'
                .($u[1] ? '<lastmod>'.$u[1]->toAtomString().'</lastmod>' : '')
                ."<changefreq>{$u[2]}</changefreq><priority>{$u[3]}</priority></url>")->implode("\n");

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n{$body}\n</urlset>\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        // Selain produksi (staging, lokal) jangan sampai terindeks mesin pencari.
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /akun',
                'Disallow: /portal-penitip',
                'Disallow: /titip-barang/status',
                'Disallow: /pembayaran',
                'Allow: /',
                '',
                'Sitemap: '.route('seo.sitemap'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
