<?php

namespace Database\Factories;

use App\Enums\RequestActivityType;
use App\Models\RequestActivity;
use App\Models\RequestSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestActivity>
 */
class RequestActivityFactory extends Factory
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
            'actor_id' => fn (array $attributes): int => RequestSubmission::query()->findOrFail($attributes['request_submission_id'])->created_by,
            'type' => RequestActivityType::RequestCreated,
            'request_comment_id' => null,
            'request_attachment_id' => null,
            'request_approval_id' => null,
            'metadata' => null,
        ];
    }
}
