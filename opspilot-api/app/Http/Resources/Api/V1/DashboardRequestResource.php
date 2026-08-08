<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'request_type' => [
                'id' => $this->requestType->id,
                'name' => $this->requestType->name,
                'slug' => $this->requestType->slug,
            ],
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'submitted_at' => $this->submitted_at,
            'cancelled_at' => $this->cancelled_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
