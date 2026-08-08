<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelRequest;
use App\Actions\CreateRequestSubmission;
use App\Actions\SubmitRequest;
use App\Actions\UpdateRequestDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequestSubmissionRequest;
use App\Http\Requests\Api\V1\StoreRequestSubmissionRequest;
use App\Http\Requests\Api\V1\UpdateRequestSubmissionRequest;
use App\Http\Resources\Api\V1\RequestSubmissionResource;
use App\Http\Resources\Api\V1\RequestSubmissionSummaryResource;
use App\Models\RequestSubmission;
use App\Models\RequestType;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RequestSubmissionController extends Controller
{
    public function index(IndexRequestSubmissionRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $workspace->requestSubmissions()->with($this->relations())->latest('id');
        if (! Gate::allows('viewAll', [RequestSubmission::class, $workspace])) {
            $query->whereBelongsTo($request->user(), 'creator');
        }

        $query->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
        $query->when($filters['request_type_id'] ?? null, fn ($query, int $requestTypeId) => $query->where('request_type_id', $requestTypeId));

        return RequestSubmissionSummaryResource::collection(
            $query->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function store(
        StoreRequestSubmissionRequest $request,
        Workspace $workspace,
        RequestType $requestType,
        CreateRequestSubmission $action,
    ): JsonResponse {
        $submission = $action->handle($workspace, $requestType, $request->user(), $request->validated('payload', []));

        return $this->response($request, $submission, 'Request draft created successfully.', 201);
    }

    public function show(Request $request, Workspace $workspace, RequestSubmission $requestSubmission): JsonResponse
    {
        Gate::authorize('view', $requestSubmission);

        return $this->response($request, $requestSubmission);
    }

    public function update(
        UpdateRequestSubmissionRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        UpdateRequestDraft $action,
    ): JsonResponse {
        return $this->response(
            $request,
            $action->handle($requestSubmission, $request->validated()),
            'Request draft updated successfully.',
        );
    }

    public function submit(
        Request $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        SubmitRequest $action,
    ): JsonResponse {
        Gate::authorize('submit', $requestSubmission);

        return $this->response($request, $action->handle($requestSubmission), 'Request submitted successfully.');
    }

    public function cancel(
        Request $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        CancelRequest $action,
    ): JsonResponse {
        Gate::authorize('cancel', $requestSubmission);

        return $this->response($request, $action->handle($requestSubmission, $request->user()), 'Request cancelled successfully.');
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['requestType:id,name,slug', 'workflow:id,name,version', 'creator:id,name,email'];
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            ...$this->relations(),
            'approvals.workflowStep:id,name,approver_type,approver_role',
            'approvals.assignees.user:id,name',
            'approvals.decidedBy:id,name',
        ];
    }

    private function response(Request $request, RequestSubmission $submission, ?string $message = null, int $status = 200): JsonResponse
    {
        $body = ['data' => RequestSubmissionResource::make($submission->load($this->detailRelations()))->resolve($request)];
        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }
}
