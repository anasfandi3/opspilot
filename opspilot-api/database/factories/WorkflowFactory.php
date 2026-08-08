<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'request_type_id' => RequestType::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'version' => 1,
            'status' => WorkflowStatus::Draft,
            'draft_guard' => 1,
            'active_guard' => null,
            'created_by' => User::factory(),
            'published_at' => null,
        ];
    }
}
