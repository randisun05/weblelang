<?php

namespace App\Events;

use App\Models\Lot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disiarkan setiap ada bid baru. Aktifkan Laravel Reverb / Pusher
 * (BROADCAST_CONNECTION) untuk pembaruan real-time; tanpa itu frontend
 * otomatis memakai polling.
 */
class BidPlaced implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Lot $lot) {}

    public function broadcastOn(): Channel
    {
        return new Channel('lots.'.$this->lot->id);
    }

    public function broadcastWith(): array
    {
        return [
            'current_price' => $this->lot->current_price,
            'bids_count' => $this->lot->bids_count,
            'ends_at' => $this->lot->ends_at->toIso8601String(),
        ];
    }
}
