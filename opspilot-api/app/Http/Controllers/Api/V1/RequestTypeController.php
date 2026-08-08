<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRequestTypeRequest;
use App\Http\Requests\Api\V1\UpdateRequestTypeRequest;
use App\Http\Resources\Api\V1\RequestTypeResource;
use App\Models\RequestType;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RequestTypeController extends Controller
{
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewRequestTypes', $workspace);
        $requestTypes = $workspace->requestTypes()->with(['creator:id,name', 'fields'])->latest()->get();

        return RequestTypeResource::collection($requestTypes);
    }

    public function store(StoreRequestTypeRequest $request, Workspace $workspace, CreateRequestType $action): JsonResponse
    {
        $requestType = $action->handle($workspace, $request->user(), $request->validated());
        $requestType->load(['creator:id,name', 'fields']);

        return response()->json([
            'data' => RequestTypeResource::make($requestType)->resolve($request),
            'message' => 'Request type created successfully.',
        ], 201);
    }

    public function show(Request $request, Workspace $workspace, RequestType $requestType): JsonResponse
    {
        Gate::authorize('viewRequestTypes', $workspace);

        return response()->json([
            'data' => RequestTypeResource::make($requestType->load(['creator:id,name', 'fields']))->resolve($request),
        ]);
    }

    public function update(UpdateRequestTypeRequest $request, Workspace $workspace, RequestType $requestType): JsonResponse
    {
        $requestType->update($request->validated());

        return response()->json([
            'data' => RequestTypeResource::make($requestType->refresh()->load(['creator:id,name', 'fields']))->resolve($request),
            'message' => 'Request type updated successfully.',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, RequestType $requestType): JsonResponse
    {
        Gate::authorize('manageRequestTypes', $workspace);
        $requestType->delete();

        return response()->json(['data' => (object) [], 'message' => 'Request type deleted successfully.']);
    }
}
