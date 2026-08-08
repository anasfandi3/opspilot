<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestApprovalInboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'position' => $this->position,
            'workflow_step' => [
                'id' => $this->workflowStep->id,
                'name' => $this->workflowStep->name,
            ],
            'request' => [
                'id' => $this->requestSubmission->id,
                'status' => $this->requestSubmission->status->value,
                'request_type' => [
                    'id' => $this->requestSubmission->requestType->id,
                    'name' => $this->requestSubmission->requestType->name,
                    'slug' => $this->requestSubmission->requestType->slug,
                ],
                'creator' => [
                    'id' => $this->requestSubmission->creator->id,
                    'name' => $this->requestSubmission->creator->name,
                    'email' => $this->requestSubmission->creator->email,
                ],
            ],
            'activated_at' => $this->activated_at,
            'decided_at' => $this->decided_at,
        ];
    }
}
