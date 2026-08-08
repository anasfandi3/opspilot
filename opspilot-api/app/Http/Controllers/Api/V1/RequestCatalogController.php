<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RequestCatalogResource;
use App\Models\RequestSubmission;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RequestCatalogController extends Controller
{
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('create', [RequestSubmission::class, $workspace]);

        $requestTypes = $workspace->requestTypes()
            ->where('is_active', true)
            ->whereHas('activeWorkflow')
            ->with('fields')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return RequestCatalogResource::collection($requestTypes);
    }
}
