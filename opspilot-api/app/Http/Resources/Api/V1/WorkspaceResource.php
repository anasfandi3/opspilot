<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\WorkspaceRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->pivot?->role ?? $this->resource->roleFor($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_id' => $this->owner_id,
            'role' => $role instanceof WorkspaceRole ? $role->value : $role,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
