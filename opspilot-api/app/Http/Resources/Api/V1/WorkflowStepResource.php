<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'approver_type' => $this->approver_type->value,
            'approver_role' => $this->approver_role?->value,
            'approver_user' => $this->whenLoaded('approverUser', fn (): ?array => $this->approverUser ? [
                'id' => $this->approverUser->id,
                'name' => $this->approverUser->name,
                'email' => $this->approverUser->email,
            ] : null),
            'condition_logic' => $this->condition_logic->value,
            'conditions' => WorkflowConditionResource::collection($this->whenLoaded('conditions')),
        ];
    }
}
