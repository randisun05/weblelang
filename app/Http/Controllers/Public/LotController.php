<?php

namespace App\Http\Controllers\Public;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Lot;
use App\Services\Auction\LotCloser;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LotController extends Controller
{
    public function show(Request $request, Lot $lot): Response
    {
        $this->ensureVisible($lot);
        $this->closeIfExpired($lot);

        $lot->load(['auction', 'item.images', 'item.category']);
        $user = $request->user();
        Seo::lot($lot);

        return Inertia::render('Public/Lots/Show', [
            'lot' => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'starting_price' => $lot->starting_price,
                'item' => [
                    'code' => $lot->item->code,
                    'title' => $lot->item->title,
                    'description' => $lot->item->description,
                    'condition' => Item::CONDITIONS[$lot->item->condition] ?? $lot->item->condition,
                    'category' => $lot->item->category?->name,
                    'estimate_low' => $lot->item->estimate_low,
                    'estimate_high' => $lot->item->estimate_high,
                    'attributes' => Present::itemAttributes($lot->item),
                    'images' => $lot->item->images->map(fn ($i) => $i->url()),
                ],
                'auction' => [
                    'slug' => $lot->auction->slug,
                    'title' => $lot->auction->title,
                    'buyer_premium_rate' => $lot->auction->buyer_premium_rate,
                    'requires_registration' => $lot->auction->requiresRegistration(),
                    'deposit_amount' => $lot->auction->deposit_amount,
                    'anti_snipe_minutes' => $lot->auction->anti_snipe_minutes,
                    'extend_minutes' => $lot->auction->extend_minutes,
                    'stream_embed' => $lot->auction->isLive() ? $lot->auction->streamEmbedUrl() : null,
                    'stream_url' => $lot->auction->isLive() ? $lot->auction->stream_url : null,
                ],
            ],
            'state' => Present::lotState($lot, $user?->id),
            'viewer' => $user ? [
                'watching' => $user->watchlist()->whereKey($lot->id)->exists(),
                'auto_bid' => $lot->autoBids()->where('user_id', $user->id)->where('is_active', true)->value('max_amount'),
                'kyc_verified' => $user->isKycVerified(),
                'email_verified' => $user->hasVerifiedEmail(),
                'is_backoffice' => $user->isBackoffice(),
                'registration' => $lot->auction->requiresRegistration()
                    ? $lot->auction->registrations()->where('user_id', $user->id)->value('status')
                    : 'not_required',
            ] : null,
            'pollSeconds' => (int) config('auction.poll_seconds', 4),
        ]);
    }

    /** Endpoint ringan untuk polling harga & riwayat bid. */
    public function state(Request $request, Lot $lot): JsonResponse
    {
        $this->ensureVisible($lot);
        $this->closeIfExpired($lot);

        return response()->json(Present::lotState($lot->fresh(), $request->user()?->id));
    }

    private function ensureVisible(Lot $lot): void
    {
        abort_unless($lot->auction()->where('status', '!=', 'draft')->exists(), 404);
    }

    /** Menutup lot secara "malas" bila cron terlambat, supaya pemenang langsung terlihat. */
    private function closeIfExpired(Lot $lot): void
    {
        // Lot lelang live hanya dibuka/ditutup oleh juru lelang.
        if ($lot->auction->isLive()) {
            return;
        }

        if (in_array($lot->status, [LotStatus::Live, LotStatus::Scheduled], true) && $lot->ends_at->isPast()) {
            app(LotCloser::class)->close($lot->id);
            $lot->refresh();
        } elseif ($lot->status === LotStatus::Scheduled && $lot->starts_at->isPast()) {
            app(LotCloser::class)->openDue();
            $lot->refresh();
        }
    }
}
