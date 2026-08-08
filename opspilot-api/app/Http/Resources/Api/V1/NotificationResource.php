<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'event' => $data['event'] ?? null,
            'message' => $data['message'] ?? null,
            'workspace' => $data['workspace'] ?? null,
            'request' => $data['request'] ?? null,
            'approval' => $data['approval'] ?? null,
            'actor' => $data['actor'] ?? null,
            'comment' => isset($data['comment']) ? ['id' => $data['comment']['id']] : null,
            'attachment' => isset($data['attachment']) ? [
                'id' => $data['attachment']['id'],
                'original_name' => $data['attachment']['original_name'],
                'mime_type' => $data['attachment']['mime_type'],
                'size_bytes' => $data['attachment']['size_bytes'],
            ] : null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
