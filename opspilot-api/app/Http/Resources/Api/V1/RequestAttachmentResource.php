<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploader' => $this->whenLoaded('uploader', fn (): array => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
