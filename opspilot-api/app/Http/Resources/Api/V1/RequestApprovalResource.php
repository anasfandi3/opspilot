<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'status' => $this->status->value,
            'workflow_step' => $this->whenLoaded('workflowStep', fn (): array => [
                'id' => $this->workflowStep->id,
                'name' => $this->workflowStep->name,
            ]),
            'approver_type' => $this->whenLoaded('workflowStep', fn (): string => $this->workflowStep->approver_type->value),
            'approver_role' => $this->whenLoaded('workflowStep', fn (): ?string => $this->workflowStep->approver_role?->value),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($assignment): array => [
                'id' => $assignment->user->id,
                'name' => $assignment->user->name,
            ])->values()),
            'decided_by' => $this->whenLoaded('decidedBy', fn (): ?array => $this->decidedBy ? [
                'id' => $this->decidedBy->id,
                'name' => $this->decidedBy->name,
            ] : null),
            'activated_at' => $this->activated_at,
            'decided_at' => $this->decided_at,
        ];
    }
}
