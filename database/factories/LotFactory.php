<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

class LotFactory extends Factory
{
    private static int $number = 0;

    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'item_id' => Item::factory()->status(ItemStatus::Listed),
            'lot_number' => ++self::$number,
            'starting_price' => 500_000,
            'reserve_price' => 1_000_000,
            'current_price' => 0,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'status' => LotStatus::Live,
        ];
    }
}
