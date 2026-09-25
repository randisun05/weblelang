<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuctionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Lelang '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'status' => AuctionStatus::Live,
            'deposit_amount' => 0,
            'buyer_premium_rate' => 5,
            'anti_snipe_minutes' => 3,
            'extend_minutes' => 3,
            'stagger_seconds' => 0,
        ];
    }
}
