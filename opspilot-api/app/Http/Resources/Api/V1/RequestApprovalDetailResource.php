<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class RequestApprovalDetailResource extends RequestApprovalResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'request' => RequestSubmissionResource::make($this->whenLoaded('requestSubmission')),
        ];
    }
}
