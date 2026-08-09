<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Support\WorkspacePermissions;
use App\Support\WorkspaceRoleMap;
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
        $role = $this->current_user_role === null
            ? app(WorkspacePermissions::class)->role($request->user(), $this->resource)?->value
            : (string) $this->current_user_role;
        $workspaceRole = $role === null ? null : WorkspaceRole::tryFrom($role);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_id' => $this->owner_id,
            'role' => $role,
            'permissions' => $workspaceRole === null ? [] : array_map(
                static fn (WorkspacePermission $permission): string => $permission->value,
                WorkspaceRoleMap::permissions($workspaceRole),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
