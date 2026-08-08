<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\Workflow;
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
            'request_type_id' => RequestType::factory(),
            'workspace_id' => fn (array $attributes): int => RequestType::query()->findOrFail($attributes['request_type_id'])->workspace_id,
            'workflow_id' => null,
            'created_by' => fn (array $attributes): int => RequestType::query()->findOrFail($attributes['request_type_id'])->created_by,
            'status' => RequestStatus::Draft,
            'payload' => [],
            'definition_snapshot' => null,
            'submitted_at' => null,
            'cancelled_at' => null,
            'resolved_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => RequestStatus::Submitted,
            'submitted_at' => now(),
        ])->afterCreating(function (RequestSubmission $submission): void {
            $requestType = $submission->requestType;
            $workflow = Workflow::factory()->active()->create([
                'workspace_id' => $requestType->workspace_id,
                'request_type_id' => $requestType->id,
                'created_by' => $requestType->created_by,
            ]);

            $submission->forceFill([
                'workflow_id' => $workflow->id,
                'definition_snapshot' => $this->snapshot($requestType),
            ])->save();
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(RequestType $requestType): array
    {
        return [
            'request_type' => [
                'id' => $requestType->id,
                'name' => $requestType->name,
                'slug' => $requestType->slug,
            ],
            'fields' => $requestType->fields->map(fn (RequestTypeField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'description' => $field->description,
                'is_required' => $field->is_required,
                'position' => $field->position,
                'config' => $field->config,
            ])->all(),
        ];
    }
}
