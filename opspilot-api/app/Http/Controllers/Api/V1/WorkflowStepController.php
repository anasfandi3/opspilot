<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReorderWorkflowSteps;
use App\Actions\SaveWorkflowStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderWorkflowStepsRequest;
use App\Http\Requests\Api\V1\StoreWorkflowStepRequest;
use App\Http\Requests\Api\V1\UpdateWorkflowStepRequest;
use App\Http\Resources\Api\V1\WorkflowStepResource;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkflowStepController extends Controller
{
    public function store(StoreWorkflowStepRequest $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, SaveWorkflowStep $action): JsonResponse
    {
        $step = $action->create($workflow, $request->validated());

        return response()->json(['data' => WorkflowStepResource::make($step)->resolve($request), 'message' => 'Workflow step created successfully.'], 201);
    }

    public function update(UpdateWorkflowStepRequest $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, WorkflowStep $step, SaveWorkflowStep $action): JsonResponse
    {
        $step = $action->update($workflow, $step, $request->validated());

        return response()->json(['data' => WorkflowStepResource::make($step)->resolve($request), 'message' => 'Workflow step updated successfully.']);
    }

    public function destroy(Request $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, WorkflowStep $step, SaveWorkflowStep $action): JsonResponse
    {
        Gate::authorize('manageWorkflows', $workspace);
        $action->delete($workflow, $step);

        return response()->json(['data' => (object) [], 'message' => 'Workflow step deleted successfully.']);
    }

    public function reorder(ReorderWorkflowStepsRequest $request, Workspace $workspace, RequestType $requestType, Workflow $workflow, ReorderWorkflowSteps $action): JsonResponse
    {
        $action->handle($workflow, $request->validated('step_ids'));
        $steps = $workflow->steps()->with(['approverUser:id,name,email', 'conditions.requestTypeField'])->get();

        return response()->json(['data' => WorkflowStepResource::collection($steps)->resolve($request), 'message' => 'Workflow steps reordered successfully.']);
    }
}
