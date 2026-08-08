<?php

namespace Database\Factories;

use App\Enums\RequestActivityType;
use App\Models\RequestActivity;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workspace;
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
            'workspace_id' => Workspace::factory(),
            'request_submission_id' => RequestSubmission::factory(),
            'actor_id' => User::factory(),
            'type' => RequestActivityType::RequestCreated,
            'request_comment_id' => null,
            'request_attachment_id' => null,
            'request_approval_id' => null,
            'metadata' => null,
        ];
    }
}
