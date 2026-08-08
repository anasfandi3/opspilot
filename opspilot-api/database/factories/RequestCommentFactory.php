<?php

namespace Database\Factories;

use App\Models\RequestComment;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Models\Workspace;
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
            'workspace_id' => Workspace::factory(),
            'request_submission_id' => RequestSubmission::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
