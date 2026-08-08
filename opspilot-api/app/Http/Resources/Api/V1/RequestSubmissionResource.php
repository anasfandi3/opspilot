<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class RequestSubmissionResource extends RequestSubmissionSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'definition_snapshot' => $this->definition_snapshot,
            'approvals' => RequestApprovalResource::collection($this->whenLoaded('approvals')),
        ];
    }
}
