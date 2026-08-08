<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestCommentResource extends JsonResource
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
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
