<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateRequestTypeField;
use App\Actions\DeleteRequestTypeField;
use App\Actions\ReorderRequestTypeFields;
use App\Actions\UpdateRequestTypeField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderRequestTypeFieldsRequest;
use App\Http\Requests\Api\V1\StoreRequestTypeFieldRequest;
use App\Http\Requests\Api\V1\UpdateRequestTypeFieldRequest;
use App\Http\Resources\Api\V1\RequestTypeFieldResource;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RequestTypeFieldController extends Controller
{
    public function store(StoreRequestTypeFieldRequest $request, Workspace $workspace, RequestType $requestType, CreateRequestTypeField $action): JsonResponse
    {
        $field = $action->handle($requestType, $request->validated());

        return response()->json([
            'data' => RequestTypeFieldResource::make($field)->resolve($request),
            'message' => 'Request type field created successfully.',
        ], 201);
    }

    public function update(UpdateRequestTypeFieldRequest $request, Workspace $workspace, RequestType $requestType, RequestTypeField $field, UpdateRequestTypeField $action): JsonResponse
    {
        $field = $action->handle($field, $request->validated());

        return response()->json([
            'data' => RequestTypeFieldResource::make($field->refresh())->resolve($request),
            'message' => 'Request type field updated successfully.',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, RequestType $requestType, RequestTypeField $field, DeleteRequestTypeField $action): JsonResponse
    {
        Gate::authorize('manageRequestTypes', $workspace);
        $action->handle($field);

        return response()->json(['data' => (object) [], 'message' => 'Request type field deleted successfully.']);
    }

    public function reorder(ReorderRequestTypeFieldsRequest $request, Workspace $workspace, RequestType $requestType, ReorderRequestTypeFields $action): JsonResponse
    {
        $action->handle($requestType, $request->validated('field_ids'));

        return response()->json([
            'data' => RequestTypeFieldResource::collection($requestType->fields()->get())->resolve($request),
            'message' => 'Request type fields reordered successfully.',
        ]);
    }
}
