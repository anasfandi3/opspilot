<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RemoveWorkspaceMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewMembers', $workspace);

        $members = $workspace->members()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();

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
}
