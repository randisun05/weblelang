<?php

namespace App\Services\Auction;

use App\Models\Lot;

/**
 * Menghitung kelipatan penawaran berdasarkan tabel bertingkat di config/auction.php.
 */
class BidIncrement
{
    /** @param array<int, int> $table */
    public function __construct(private array $table = [])
    {
        $this->table = $table ?: config('auction.increments');
        ksort($this->table);
    }

    public function for(int $price): int
    {
        $increment = reset($this->table);

        foreach ($this->table as $threshold => $step) {
            if ($price >= $threshold) {
                $increment = $step;
            }
        }

        return $increment;
    }

    /** Nominal minimum yang sah untuk bid berikutnya. */
    public function minimumNextBid(Lot $lot): int
    {
        if ($lot->bids_count === 0) {
            return $lot->starting_price;
        }

        return $this->after($lot->current_price);
    }

    public function after(int $price): int
    {
        return $price + $this->for($price);
    }
}
