<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\Consignor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consignor_id' => Consignor::factory(),
            'category_id' => Category::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'condition' => 'bekas_baik',
            'specs' => ['merk' => fake()->company()],
            'estimate_low' => 1_000_000,
            'estimate_high' => 2_000_000,
            'reserve_price' => 1_000_000,
            'received_at' => now(),
            'status' => ItemStatus::Approved,
        ];
    }

    public function status(ItemStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
