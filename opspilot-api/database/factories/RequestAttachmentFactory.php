<?php

namespace Database\Factories;

use App\Models\RequestAttachment;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workspace;
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
            'workspace_id' => Workspace::factory(),
            'request_submission_id' => RequestSubmission::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'requests/'.fake()->uuid(),
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1, 10240),
        ];
    }
}
