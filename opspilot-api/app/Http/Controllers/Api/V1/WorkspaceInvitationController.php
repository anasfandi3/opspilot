<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptWorkspaceInvitation;
use App\Actions\CreateWorkspaceInvitation;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkspaceInvitationRequest;
use App\Http\Resources\Api\V1\WorkspaceInvitationResource;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceInvitationController extends Controller
{
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('manageInvitations', $workspace);

        return WorkspaceInvitationResource::collection($workspace->invitations()->latest()->get());
    }

    public function store(StoreWorkspaceInvitationRequest $request, Workspace $workspace, CreateWorkspaceInvitation $action): JsonResponse
    {
        $result = $action->create($workspace, $request->user(), $request->validated('email'), WorkspaceRole::from($request->validated('role')));
        $data = WorkspaceInvitationResource::make($result['invitation'])->resolve($request);
        if (config('workspaces.expose_invitation_tokens')) {
            $data['token'] = $result['token'];
        }

        return response()->json(['data' => $data, 'message' => 'Invitation created successfully.'], 201);
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation, CreateWorkspaceInvitation $action): JsonResponse
    {
        Gate::authorize('revokeInvitation', $workspace);
        $action->revoke($invitation);

        return response()->json(['data' => (object) [], 'message' => 'Invitation revoked successfully.']);
    }

    public function resend(Request $request, Workspace $workspace, WorkspaceInvitation $invitation, CreateWorkspaceInvitation $action): JsonResponse
    {
        Gate::authorize('revokeInvitation', $workspace);
        $token = $action->resend($invitation);
        $data = WorkspaceInvitationResource::make($invitation->refresh())->resolve($request);
        if (config('workspaces.expose_invitation_tokens')) {
            $data['token'] = $token;
        }

        return response()->json(['data' => $data, 'message' => 'Invitation resent successfully.']);
    }

    public function accept(Request $request, string $token, AcceptWorkspaceInvitation $action): JsonResponse
    {
        $invitation = $action->handle($request->user(), $token);

        return response()->json(['data' => WorkspaceInvitationResource::make($invitation)->resolve($request), 'message' => 'Invitation accepted successfully.']);
    }
}
