<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateRequestComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequestCollaborationRequest;
use App\Http\Requests\Api\V1\StoreRequestCommentRequest;
use App\Http\Resources\Api\V1\RequestCommentResource;
use App\Models\RequestSubmission;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestCommentController extends Controller
{
    public function index(
        IndexRequestCollaborationRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return RequestCommentResource::collection(
            $requestSubmission->comments()->with('author:id,name')->latest('created_at')->latest('id')
                ->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function store(
        StoreRequestCommentRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        CreateRequestComment $action,
    ): JsonResponse {
        $comment = $action->handle($requestSubmission, $request->user(), $request->validated('body'));

        return response()->json([
            'data' => RequestCommentResource::make($comment->load('author:id,name'))->resolve($request),
            'message' => 'Comment added successfully.',
        ], 201);
    }
}
