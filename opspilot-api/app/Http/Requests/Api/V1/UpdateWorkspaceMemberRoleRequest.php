<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\WorkspaceRole;
use App\Support\WorkspacePermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignRoles', $this->route('workspace'));
    }

    public function rules(): array
    {
        $actorRole = app(WorkspacePermissions::class)->role($this->user(), $this->route('workspace'));
        $roles = [WorkspaceRole::Approver, WorkspaceRole::Requester, WorkspaceRole::Auditor];
        if ($actorRole === WorkspaceRole::Owner) {
            $roles[] = WorkspaceRole::Admin;
        }

        return ['role' => ['required', Rule::enum(WorkspaceRole::class)->only($roles)]];
    }
}
