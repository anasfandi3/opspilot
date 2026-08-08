<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequestCollaborationRequest;
use App\Http\Resources\Api\V1\RequestActivityResource;
use App\Models\RequestSubmission;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestActivityController extends Controller
{
    public function index(
        IndexRequestCollaborationRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return RequestActivityResource::collection(
            $requestSubmission->activities()
                ->with([
                    'actor:id,name',
                    'comment.author:id,name',
                    'attachment.uploader:id,name',
                    'approval.workflowStep:id,name',
                ])
                ->latest('created_at')->latest('id')
                ->paginate($filters['per_page'] ?? 50)->withQueryString(),
        );
    }
}
