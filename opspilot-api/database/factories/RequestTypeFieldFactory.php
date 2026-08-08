<?php

namespace Database\Factories;

use App\Enums\RequestFieldType;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestTypeFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_type_id' => RequestType::factory(),
            'key' => fake()->unique()->lexify('field_????????'),
            'label' => fake()->words(2, true),
            'type' => RequestFieldType::Text,
            'description' => fake()->optional()->sentence(),
            'is_required' => false,
            'position' => fake()->numberBetween(1, 100),
            'config' => null,
        ];
    }
}
