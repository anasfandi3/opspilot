<?php

namespace Database\Factories;

use App\Models\RequestApproval;
use App\Models\RequestApprovalAssignee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApprovalAssignee>
 */
class RequestApprovalAssigneeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_approval_id' => RequestApproval::factory(),
            'user_id' => User::factory(),
        ];
    }
}
