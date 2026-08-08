<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestSubmissionSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'payload' => (object) $this->payload,
            'request_type' => $this->whenLoaded('requestType', fn (): array => [
                'id' => $this->requestType->id,
                'name' => $this->requestType->name,
                'slug' => $this->requestType->slug,
            ]),
            'workflow' => $this->whenLoaded('workflow', fn (): ?array => $this->workflow ? [
                'id' => $this->workflow->id,
                'version' => $this->workflow->version,
                'name' => $this->workflow->name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'submitted_at' => $this->submitted_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
