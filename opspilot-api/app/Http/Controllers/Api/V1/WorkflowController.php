<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CloneWorkflow;
use App\Actions\CreateWorkflow;
use App\Actions\DeleteWorkflow;
use App\Actions\PublishWorkflow;
use App\Actions\UpdateWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\UpdateWorkflowRequest;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkflowController extends Controller
{
    public function index(Request $request, Workspace $workspace, RequestType $requestType): AnonymousResourceCollection
    {
        Gate::authorize('viewWorkflows', $workspace);

        return WorkflowResource::collection($requestType->workflows()->with($this->relations())->get());
    }

    public function store(StoreWorkflowRequest $request, Workspace $workspace, RequestType $requestType, CreateWorkflow $action): JsonResponse
    {
        $workflow = $action->handle($workspace, $requestType, $request->user(), $request->validated());

        return $this->response($request, $workflow, 'Workflow created successfully.', 201);
    }

    public function show(Request $request, Workspace $workspace, RequestType $requestType, Workflow $workflow): JsonResponse
    {
        Gate::authorize('viewWorkflows', $workspace);

        return $this->response($request, $workflow);
    }

    public function update(UpdateWorkflowRequest $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, UpdateWorkflow $action): JsonResponse
    {
        return $this->response($request, $action->handle($workflow, $request->validated()), 'Workflow updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, DeleteWorkflow $action): JsonResponse
    {
        Gate::authorize('manageWorkflows', $workspace);
        $action->handle($workflow);

        return response()->json(['data' => (object) [], 'message' => 'Workflow deleted successfully.']);
    }

    public function publish(Request $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, PublishWorkflow $action): JsonResponse
    {
        Gate::authorize('manageWorkflows', $workspace);

        return $this->response($request, $action->handle($workflow), 'Workflow published successfully.');
    }

    public function clone(Request $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, CloneWorkflow $action): JsonResponse
    {
        Gate::authorize('manageWorkflows', $workspace);

        return $this->response($request, $action->handle($workflow, $request->user()), 'Workflow cloned successfully.', 201);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return ['creator:id,name', 'steps.approverUser:id,name,email', 'steps.conditions.requestTypeField'];
    }

    private function response(Request $request, Workflow $workflow, ?string $message = null, int $status = 200): JsonResponse
    {
        $body = ['data' => WorkflowResource::make($workflow->load($this->relations()))->resolve($request)];
        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }
}
