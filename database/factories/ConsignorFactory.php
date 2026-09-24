<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ConsignorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '0813'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'bank_name' => 'BCA',
            'bank_account' => fake()->numerify('##########'),
            'bank_holder' => fake()->name(),
            'commission_rate' => 10,
        ];
    }
}
