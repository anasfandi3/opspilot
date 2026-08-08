<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestSubmission>
 */
class RequestSubmissionFactory extends Factory
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
            'request_type_id' => RequestType::factory(),
            'workflow_id' => null,
            'created_by' => User::factory(),
            'status' => RequestStatus::Draft,
            'payload' => [],
            'definition_snapshot' => null,
            'submitted_at' => null,
            'cancelled_at' => null,
            'resolved_at' => null,
        ];
    }
}
