<?php

namespace Database\Factories;

use App\Models\RequestComment;
use App\Models\RequestSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestComment>
 */
class RequestCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_submission_id' => RequestSubmission::factory(),
            'workspace_id' => fn (array $attributes): int => RequestSubmission::query()->findOrFail($attributes['request_submission_id'])->workspace_id,
            'author_id' => fn (array $attributes): int => RequestSubmission::query()->findOrFail($attributes['request_submission_id'])->created_by,
            'body' => fake()->sentence(),
        ];
    }
}
