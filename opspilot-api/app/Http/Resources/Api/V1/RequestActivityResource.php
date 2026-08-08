<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestActivityResource extends JsonResource
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
            'type' => $this->type->value,
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ] : null),
            'metadata' => $this->metadata,
            'comment' => $this->whenLoaded('comment', fn (): ?array => $this->comment ? [
                'id' => $this->comment->id,
                'body' => $this->comment->body,
                'author' => [
                    'id' => $this->comment->author->id,
                    'name' => $this->comment->author->name,
                ],
            ] : null),
            'attachment' => $this->whenLoaded('attachment', fn (): ?array => $this->attachment ? [
                'id' => $this->attachment->id,
                'original_name' => $this->attachment->original_name,
                'mime_type' => $this->attachment->mime_type,
                'size_bytes' => $this->attachment->size_bytes,
                'uploader' => [
                    'id' => $this->attachment->uploader->id,
                    'name' => $this->attachment->uploader->name,
                ],
            ] : null),
            'approval' => $this->whenLoaded('approval', fn (): ?array => $this->approval ? [
                'id' => $this->approval->id,
                'status' => $this->approval->status->value,
                'position' => $this->approval->position,
                'workflow_step' => [
                    'id' => $this->approval->workflowStep->id,
                    'name' => $this->approval->workflowStep->name,
                ],
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
