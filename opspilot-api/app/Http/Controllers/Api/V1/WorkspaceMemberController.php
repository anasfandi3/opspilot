<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RemoveWorkspaceMember;
use App\Actions\UpdateWorkspaceMemberRole;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateWorkspaceMemberRoleRequest;
use App\Http\Resources\Api\V1\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceRoleLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkspaceRoleLookup $roles): AnonymousResourceCollection
    {
        Gate::authorize('viewMembers', $workspace);

        $members = $workspace->members()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();
        $memberRoles = $roles->forWorkspace($workspace, $members);
        $members->each(fn (User $member) => $member->setAttribute(
            'workspace_role',
            $memberRoles->get($member->id),
        ));

        return WorkspaceMemberResource::collection($members);
    }

    public function destroy(
        Workspace $workspace,
        User $user,
        RemoveWorkspaceMember $removeWorkspaceMember,
    ): JsonResponse {
        Gate::authorize('removeMember', [$workspace, $user]);

        $removeWorkspaceMember->handle($workspace, $user);

        return response()->json([
            'data' => (object) [],
            'message' => 'Workspace member removed successfully.',
        ]);
    }

    public function updateRole(UpdateWorkspaceMemberRoleRequest $request, Workspace $workspace, User $user, UpdateWorkspaceMemberRole $action): JsonResponse
    {
        $action->handle($workspace, $request->user(), $user, WorkspaceRole::from($request->validated('role')));

        return response()->json([
            'data' => WorkspaceMemberResource::make(
                $user->setAttribute('workspace_role', $request->validated('role'))
                    ->setRelation('pivot', $workspace->membershipFor($user)),
            )->resolve($request),
            'message' => 'Workspace member role updated successfully.',
        ]);
    }
}
