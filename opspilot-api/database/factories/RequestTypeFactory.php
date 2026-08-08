<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'workspace_id' => Workspace::factory(),
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->randomNumber(5),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => fn (array $attributes): int => Workspace::query()->findOrFail($attributes['workspace_id'])->owner_id,
        ];
    }
}
