<?php

namespace App\Http\Controllers\Public;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Consignor;
use App\Models\Item;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal read-only untuk penitip: status barang, harga lelang berjalan, dan settlement.
 * Diakses lewat link bertanda tangan (middleware `signed`) tanpa perlu akun.
 */
class ConsignorPortalController extends Controller
{
    public function __invoke(Request $request, Consignor $consignor): Response
    {
        abort_unless($consignor->portal_nonce && hash_equals($consignor->portal_nonce, (string) $request->query('k')), 403, 'Link portal sudah tidak berlaku. Minta link baru ke admin.');

        $consignor->load([
            'items' => fn ($q) => $q->latest()->with('images', 'currentLot.auction:id,title,slug,status,method'),
            'settlements' => fn ($q) => $q->latest()->with('invoice.lot.item:id,title'),
        ]);

        return Inertia::render('Public/ConsignorPortal', [
            'consignor' => [
                'code' => $consignor->code,
                'name' => $consignor->name,
                'commission_rate' => $consignor->commission_rate,
                'bank' => trim($consignor->bank_name.' '.$consignor->maskedBankAccount()),
            ],
            'items' => $consignor->items->map(function (Item $item) {
                $lot = $item->currentLot;

                return [
                    'code' => $item->code,
                    'title' => $item->title,
                    'image' => $item->images->first()?->url(),
                    'reserve_price' => $item->reserve_price,
                    'status' => Present::status($item->status),
                    'lot' => $lot && $lot->status !== LotStatus::Cancelled ? [
                        'id' => $lot->id,
                        'auction' => $lot->auction->title,
                        'public' => $lot->auction->isPublic(),
                        'current_price' => Present::visiblePrice($lot),
                        'bids_count' => $lot->isConcealed() ? null : $lot->bids_count,
                        'ends_at' => $lot->ends_at->toIso8601String(),
                        'status' => Present::status($lot->status),
                    ] : null,
                ];
            }),
            'settlements' => $consignor->settlements->map(fn ($s) => [
                'number' => $s->number,
                'title' => $s->invoice->lot->item->title,
                'hammer_price' => $s->hammer_price,
                'commission_rate' => $s->commission_rate,
                'commission' => $s->commission,
                'net_amount' => $s->net_amount,
                'status' => Present::status($s->status),
                'paid_at' => $s->paid_at?->toIso8601String(),
            ]),
            'expiresAt' => now()->setTimestamp((int) $request->query('expires'))->toIso8601String(),
        ]);
    }
}
