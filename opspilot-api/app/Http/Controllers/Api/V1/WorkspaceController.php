<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateWorkspace;
use App\Actions\RemoveWorkspaceMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkspaceRequest;
use App\Http\Requests\Api\V1\UpdateWorkspaceRequest;
use App\Http\Resources\Api\V1\WorkspaceResource;
use App\Models\Workspace;
use App\Support\WorkspaceRoleLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceRoleLookup $roles): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Workspace::class);

        $workspaces = $request->user()->workspaces()
            ->latest('workspaces.created_at')
            ->get();
        $workspaceRoles = $roles->forUser($request->user(), $workspaces);
        $workspaces->each(fn (Workspace $workspace) => $workspace->setAttribute(
            'current_user_role',
            $workspaceRoles->get($workspace->id),
        ));

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): JsonResponse
    {
        $workspace = $createWorkspace->handle($request->user(), $request->validated('name'));

        return response()->json([
            'data' => WorkspaceResource::make($workspace)->resolve($request),
            'message' => 'Workspace created successfully.',
        ], 201);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        return response()->json([
            'data' => WorkspaceResource::make($workspace)->resolve($request),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace->update($request->validated());

        return response()->json([
            'data' => WorkspaceResource::make($workspace->refresh())->resolve($request),
            'message' => 'Workspace updated successfully.',
        ]);
    }

    public function switchWorkspace(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('switchTo', $workspace);

        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return response()->json([
            'data' => WorkspaceResource::make($workspace)->resolve($request),
            'message' => 'Active workspace changed successfully.',
        ]);
    }

    public function leave(
        Request $request,
        Workspace $workspace,
        RemoveWorkspaceMember $removeWorkspaceMember,
    ): JsonResponse {
        Gate::authorize('leave', $workspace);

        $removeWorkspaceMember->handle($workspace, $request->user());

        return response()->json([
            'data' => (object) [],
            'message' => 'Workspace left successfully.',
        ]);
    }
}
