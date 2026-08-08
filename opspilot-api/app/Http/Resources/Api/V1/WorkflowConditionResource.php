<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'field' => [
                'id' => $this->requestTypeField->id,
                'key' => $this->requestTypeField->key,
                'label' => $this->requestTypeField->label,
                'type' => $this->requestTypeField->type->value,
            ],
            'operator' => $this->operator->value,
            'value' => $this->value,
            'position' => $this->position,
        ];
    }
}
