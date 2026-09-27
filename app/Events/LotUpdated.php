<?php

namespace App\Events;

use App\Enums\AuctionMethod;
use App\Models\Lot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinyal real-time (Laravel Reverb) bahwa sebuah lot berubah: bid baru, panggilan juru lelang,
 * dibuka, ditutup, atau dibatalkan.
 *
 * Sengaja TIDAK membawa harga/penawar: browser cukup mengambil ulang state lewat endpoint
 * `lots.state`, sehingga aturan penyembunyian (lelang tertutup, reserve price) tetap satu pintu
 * di server. Tanpa websocket, halaman tetap berjalan dengan polling.
 */
class LotUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const BID = 'bid';

    public const CALL = 'call';

    public const OPENED = 'opened';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public int $lotId;

    public int $auctionId;

    public bool $sealedBid;

    public function __construct(Lot $lot, public string $reason)
    {
        $this->lotId = $lot->id;
        $this->auctionId = $lot->auction_id;
        // Bid di lelang tertutup tidak disiarkan: waktu masuknya pun bisa jadi petunjuk bagi peserta lain.
        $this->sealedBid = $reason === self::BID && $lot->method() === AuctionMethod::Sealed;
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('lots.'.$this->lotId), new Channel('auctions.'.$this->auctionId)];
    }

    public function broadcastAs(): string
    {
        return 'lot.updated';
    }

    public function broadcastWith(): array
    {
        return ['lot_id' => $this->lotId, 'reason' => $this->reason];
    }

    public function broadcastWhen(): bool
    {
        return ! $this->sealedBid;
    }
}
