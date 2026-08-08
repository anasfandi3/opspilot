<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveRequestApproval;
use App\Actions\RejectRequestApproval;
use App\Enums\RequestApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequestApprovalRequest;
use App\Http\Resources\Api\V1\RequestApprovalDetailResource;
use App\Http\Resources\Api\V1\RequestApprovalInboxResource;
use App\Http\Resources\Api\V1\RequestApprovalResource;
use App\Models\RequestApproval;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RequestApprovalController extends Controller
{
    public function index(IndexRequestApprovalRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $workspace->approvals()
            ->whereHas('assignees', fn ($query) => $query->whereBelongsTo($request->user()))
            ->where('status', $filters['status'] ?? RequestApprovalStatus::Pending->value)
            ->with([
                'workflowStep:id,name',
                'requestSubmission:id,request_type_id,created_by,status',
                'requestSubmission.requestType:id,name,slug',
                'requestSubmission.creator:id,name,email',
            ])
            ->orderByDesc('activated_at')
            ->latest('id');

        return RequestApprovalInboxResource::collection(
            $query->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function show(Request $request, Workspace $workspace, RequestApproval $approval): JsonResponse
    {
        Gate::authorize('view', $approval);

        return response()->json([
            'data' => RequestApprovalDetailResource::make($approval->load($this->detailRelations()))->resolve($request),
        ]);
    }

    public function approve(
        Request $request,
        Workspace $workspace,
        RequestApproval $approval,
        ApproveRequestApproval $action,
    ): JsonResponse {
        Gate::authorize('approve', $approval);
        $approval = $action->handle($approval, $request->user());

        return response()->json([
            'data' => RequestApprovalResource::make($approval->load($this->approvalRelations()))->resolve($request),
            'message' => 'Request approval approved successfully.',
        ]);
    }

    public function reject(
        Request $request,
        Workspace $workspace,
        RequestApproval $approval,
        RejectRequestApproval $action,
    ): JsonResponse {
        Gate::authorize('reject', $approval);
        $approval = $action->handle($approval, $request->user());

        return response()->json([
            'data' => RequestApprovalResource::make($approval->load($this->approvalRelations()))->resolve($request),
            'message' => 'Request approval rejected successfully.',
        ]);
    }

    /** @return list<string> */
    private function approvalRelations(): array
    {
        return ['workflowStep:id,name,approver_type,approver_role', 'assignees.user:id,name', 'decidedBy:id,name'];
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            ...$this->approvalRelations(),
            'requestSubmission.requestType:id,name,slug',
            'requestSubmission.workflow:id,name,version',
            'requestSubmission.creator:id,name,email',
            'requestSubmission.approvals.workflowStep:id,name,approver_type,approver_role',
            'requestSubmission.approvals.assignees.user:id,name',
            'requestSubmission.approvals.decidedBy:id,name',
        ];
    }
}
