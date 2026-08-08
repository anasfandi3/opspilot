<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Workspace;
use App\Support\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workspace = Workspace::query()->findOrFail($this->pivot->workspace_id);
        $role = app(WorkspacePermissions::class)->role($this->resource, $workspace);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'roles' => $role ? [$role->value] : [],
            'joined_at' => $this->pivot->joined_at,
        ];
    }
}
