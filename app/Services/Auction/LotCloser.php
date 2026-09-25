<?php

namespace App\Services\Auction;

use App\Enums\AuctionStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\LotEndingSoonNotification;
use App\Notifications\LotWonNotification;
use App\Services\AuditLogger;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;

/**
 * Membuka & menutup lot sesuai jadwal. Dipanggil scheduler tiap menit
 * (`php artisan auctions:tick`) dan aman dipanggil berkali-kali.
 */
class LotCloser
{
    public function __construct(private InvoiceService $invoices) {}

    /** @return array{opened: int, closed: int, reminded: int, expired: int} */
    public function tick(): array
    {
        return [
            'opened' => $this->openDue(),
            'closed' => $this->closeDue(),
            'reminded' => $this->notifyEndingSoon(),
            'expired' => $this->invoices->cancelOverdue(),
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

        return Lot::where('status', LotStatus::Scheduled)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->whereHas('auction', fn ($q) => $q->whereIn('status', [AuctionStatus::Published, AuctionStatus::Live]))
            ->update(['status' => LotStatus::Live, 'updated_at' => $now]);
    }

    public function closeDue(): int
    {
        $ids = Lot::whereIn('status', [LotStatus::Live, LotStatus::Scheduled])
            ->where('ends_at', '<=', now())
            ->whereHas('auction', fn ($q) => $q->whereIn('status', [AuctionStatus::Published, AuctionStatus::Live]))
            ->pluck('id');

        $closed = 0;
        foreach ($ids as $id) {
            $closed += $this->close($id) ? 1 : 0;
        }

        $this->closeFinishedAuctions();

        return $closed;
    }

    public function close(int $lotId): bool
    {
        return DB::transaction(function () use ($lotId) {
            $lot = Lot::whereKey($lotId)->lockForUpdate()->first();

            if (! $lot || ! in_array($lot->status, [LotStatus::Live, LotStatus::Scheduled], true) || $lot->ends_at->isFuture()) {
                return false;
            }

            $lot->load('auction', 'item');

            if ($lot->reserveMet()) {
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
