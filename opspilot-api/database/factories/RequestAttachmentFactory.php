<?php

namespace Database\Factories;

use App\Models\RequestAttachment;
use App\Models\RequestSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestAttachment>
 */
class RequestAttachmentFactory extends Factory
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
            'uploaded_by' => fn (array $attributes): int => RequestSubmission::query()->findOrFail($attributes['request_submission_id'])->created_by,
            'disk' => 'local',
            'path' => 'requests/'.fake()->uuid(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1, 10240),
        ];
    }
}
