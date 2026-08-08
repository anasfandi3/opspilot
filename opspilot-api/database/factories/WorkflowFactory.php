<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_type_id' => RequestType::factory(),
            'workspace_id' => fn (array $attributes): int => RequestType::query()->findOrFail($attributes['request_type_id'])->workspace_id,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'version' => 1,
            'status' => WorkflowStatus::Draft,
            'draft_guard' => 1,
            'active_guard' => null,
            'created_by' => fn (array $attributes): int => RequestType::query()->findOrFail($attributes['request_type_id'])->created_by,
            'published_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkflowStatus::Active,
            'draft_guard' => null,
            'active_guard' => 1,
            'published_at' => now(),
        ]);
    }
}
