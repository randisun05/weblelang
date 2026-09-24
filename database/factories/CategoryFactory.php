<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'attribute_schema' => [
                ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'required' => false],
            ],
        ];
    }
}
