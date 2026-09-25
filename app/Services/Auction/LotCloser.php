<?php

namespace App\Services\Auction;

use App\Enums\AuctionMethod;
use App\Enums\AuctionStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\LotEndingSoonNotification;
use App\Notifications\LotWonNotification;
use App\Payments\PayoutService;
use App\Services\AuditLogger;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * Membuka & menutup lot sesuai jadwal. Dipanggil scheduler tiap menit
 * (`php artisan auctions:tick`) dan aman dipanggil berkali-kali.
 */
class LotCloser
{
    public function __construct(private InvoiceService $invoices, private PayoutService $payouts) {}

    /** @return array{opened: int, closed: int, reminded: int, expired: int, refunded: int} */
    public function tick(): array
    {
        return [
            'opened' => $this->openDue(),
            'closed' => $this->closeDue(),
            'reminded' => $this->notifyEndingSoon(),
            'expired' => $this->invoices->cancelOverdue(),
            'refunded' => $this->payouts->refundDueDeposits(),
        ];
    }

    /**
     * Mengingatkan pemantau & penawar bahwa lot akan segera berakhir (sekali per lot).
     */
    public function notifyEndingSoon(): int
    {
        $minutes = (int) config('auction.ending_soon_minutes', 15);
        if ($minutes <= 0) {
            return 0;
        }

        $lots = Lot::with('item', 'watchers')
            ->where('status', LotStatus::Live)
            ->whereHas('auction', fn ($q) => $q->where('method', '!=', AuctionMethod::Live))
            ->whereNull('ending_notified_at')
            ->where('ends_at', '<=', now()->addMinutes($minutes))
            ->where('ends_at', '>', now())
            ->get();

        foreach ($lots as $lot) {
            // Tandai dulu (atomik) supaya dua proses scheduler tidak mengirim dua kali.
            $claimed = Lot::whereKey($lot->id)->whereNull('ending_notified_at')->update(['ending_notified_at' => now()]);
            if (! $claimed) {
                continue;
            }

            $bidderIds = Bid::where('lot_id', $lot->id)->distinct()->pluck('user_id');
            $recipients = $lot->watchers->pluck('id')->merge($bidderIds)->unique();

            User::whereIn('id', $recipients)->where('is_blocked', false)->get()
                ->each(fn (User $user) => $user->notify(new LotEndingSoonNotification($lot)));
        }

        return $lots->count();
    }

    public function openDue(): int
    {
        $now = now();

        Auction::where('status', AuctionStatus::Published)
            ->where('starts_at', '<=', $now)
            ->update(['status' => AuctionStatus::Live]);

        // Lot pada lelang live dibuka manual oleh juru lelang, bukan oleh jadwal.
        return Lot::where('status', LotStatus::Scheduled)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->whereHas('auction', fn ($q) => $q->whereIn('status', [AuctionStatus::Published, AuctionStatus::Live])
                ->where('method', '!=', AuctionMethod::Live))
            ->update(['status' => LotStatus::Live, 'updated_at' => $now]);
    }

    public function closeDue(): int
    {
        $ids = Lot::whereIn('status', [LotStatus::Live, LotStatus::Scheduled])
            ->where('ends_at', '<=', now())
            ->whereHas('auction', fn ($q) => $q->whereIn('status', [AuctionStatus::Published, AuctionStatus::Live])
                ->where('method', '!=', AuctionMethod::Live))
            ->pluck('id');

        $closed = 0;
        foreach ($ids as $id) {
            $closed += $this->close($id) ? 1 : 0;
        }

        $this->closeFinishedAuctions();

        return $closed;
    }

    /** @param  bool  $force  tutup sekarang meski waktu belum habis (palu juru lelang). */
    public function close(int $lotId, bool $force = false): bool
    {
        return DB::transaction(function () use ($lotId, $force) {
            $lot = Lot::whereKey($lotId)->lockForUpdate()->first();

            if (! $lot || ! in_array($lot->status, [LotStatus::Live, LotStatus::Scheduled], true) || (! $force && $lot->ends_at->isFuture())) {
                return false;
            }

            $lot->load('auction', 'item');

            if ($lot->method() === AuctionMethod::Sealed) {
                $this->resolveSealedWinner($lot);
            }

            if ($lot->reserveMet()) {
                $lot->sold_via ??= $lot->method() === AuctionMethod::Live ? 'live' : 'bid';
                $lot->status = LotStatus::Sold;
                $lot->winning_bid_id = Bid::where('lot_id', $lot->id)
                    ->where('user_id', $lot->leader_id)
                    ->orderByDesc('amount')->orderByDesc('id')->value('id');
                $lot->item->forceFill(['status' => ItemStatus::Sold])->save();
            } else {
                $lot->status = LotStatus::Unsold;
                $lot->item->forceFill(['status' => ItemStatus::Unsold])->save();
            }

            $lot->closed_at = now();
            $lot->save();

            if ($lot->status === LotStatus::Sold) {
                $invoice = $this->invoices->createForLot($lot);
                $invoice->setRelation('lot', $lot);
                $lot->leader?->notify(new LotWonNotification($invoice));
            }

            AuditLogger::log('lot.closed', $lot, [
                'status' => $lot->status->value,
                'price' => $lot->current_price,
                'reserve' => $lot->reserve_price,
            ]);

            return true;
        });
    }

    /**
     * Membuka amplop penawaran tertutup: penawaran efektif tiap peserta = yang terakhir dikirim;
     * tertinggi menang, jika sama yang lebih dulu mengirim penawaran finalnya.
     */
    private function resolveSealedWinner(Lot $lot): void
    {
        $finalBidIds = Bid::where('lot_id', $lot->id)->selectRaw('max(id)')->groupBy('user_id');

        $winner = Bid::whereIn('id', $finalBidIds)->orderByDesc('amount')->orderBy('id')->first();

        if ($winner) {
            $lot->current_price = $winner->amount;
            $lot->leader_id = $winner->user_id;
        }
    }

    // ---- Lelang live (dipandu juru lelang) ---------------------------------

    /** Juru lelang membuka lot berikutnya. Hanya satu lot live per sesi. */
    public function openLive(Lot $lot): void
    {
        DB::transaction(function () use ($lot) {
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $auction = $lot->auction;

            if (! $auction->isLive() || ! in_array($auction->status, [AuctionStatus::Published, AuctionStatus::Live], true)) {
                throw new BidException('Sesi ini bukan lelang live yang sudah terbit.');
            }
            if ($lot->status !== LotStatus::Scheduled) {
                throw new BidException('Lot ini sudah dibuka atau ditutup.');
            }
            if ($auction->lots()->where('status', LotStatus::Live)->exists()) {
                throw new BidException('Tutup lot yang sedang berjalan terlebih dahulu.');
            }

            $auction->forceFill(['status' => AuctionStatus::Live])->save();
            // ends_at hanya batas pengaman; penutupan ditentukan palu juru lelang.
            $lot->forceFill([
                'status' => LotStatus::Live, 'starts_at' => now(), 'ends_at' => now()->addHours(6),
                'live_calls' => 0, 'live_called_at' => null,
            ])->save();

            AuditLogger::log('lot.live_opened', $lot);
        });
    }

    /** Panggilan "pertama" lalu "kedua". Bid baru mereset panggilan ke nol. */
    public function callLive(Lot $lot): int
    {
        return DB::transaction(function () use ($lot) {
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! $lot->auction->isLive() || $lot->status !== LotStatus::Live) {
                throw new BidException('Lot tidak sedang berjalan.');
            }
            if ($lot->live_calls >= 2) {
                throw new BidException('Sudah panggilan kedua — ketuk palu atau tunggu penawaran baru.');
            }

            $lot->forceFill(['live_calls' => $lot->live_calls + 1, 'live_called_at' => now()])->save();
            AuditLogger::log('lot.live_call', $lot, ['call' => $lot->live_calls, 'price' => $lot->current_price]);

            return $lot->live_calls;
        });
    }

    /**
     * Ketuk palu: hanya setelah panggilan kedua. Karena bid baru mereset panggilan,
     * bid yang masuk di detik terakhir otomatis membatalkan ketukan palu yang terlambat.
     */
    public function hammer(Lot $lot): Lot
    {
        return DB::transaction(function () use ($lot) {
            $locked = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! $locked->auction->isLive() || $locked->status !== LotStatus::Live) {
                throw new BidException('Lot tidak sedang berjalan.');
            }
            if ($locked->live_calls < 2) {
                throw new BidException('Lakukan panggilan pertama dan kedua sebelum mengetuk palu.');
            }

            $this->close($locked->id, force: true);
            $this->closeFinishedAuctions();

            return $locked->fresh();
        });
    }

    /** Membatalkan lot (mis. barang ditarik penitip sebelum ditutup). */
    public function cancel(Lot $lot, string $reason): void
    {
        DB::transaction(function () use ($lot, $reason) {
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lot->status, [LotStatus::Live, LotStatus::Scheduled], true)) {
                throw new BidException('Lot yang sudah ditutup tidak dapat dibatalkan.');
            }

            $lot->forceFill(['status' => LotStatus::Cancelled, 'closed_at' => now()])->save();
            $lot->item->forceFill(['status' => ItemStatus::Approved])->save();

            AuditLogger::log('lot.cancelled', $lot, ['reason' => $reason]);
        });
    }

    private function closeFinishedAuctions(): void
    {
        Auction::where('status', AuctionStatus::Live)
            ->whereDoesntHave('lots', fn ($q) => $q->whereIn('status', [LotStatus::Live, LotStatus::Scheduled]))
            ->update(['status' => AuctionStatus::Closed]);
    }
}
