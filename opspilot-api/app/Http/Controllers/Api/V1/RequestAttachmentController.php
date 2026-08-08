<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreRequestAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequestCollaborationRequest;
use App\Http\Requests\Api\V1\StoreRequestAttachmentRequest;
use App\Http\Resources\Api\V1\RequestAttachmentResource;
use App\Models\RequestAttachment;
use App\Models\RequestSubmission;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestAttachmentController extends Controller
{
    public function index(
        IndexRequestCollaborationRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return RequestAttachmentResource::collection(
            $requestSubmission->attachments()->with('uploader:id,name')->latest('created_at')->latest('id')
                ->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function store(
        StoreRequestAttachmentRequest $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        StoreRequestAttachment $action,
    ): JsonResponse {
        $attachment = $action->handle($requestSubmission, $request->user(), $request->file('file'));

        return response()->json([
            'data' => RequestAttachmentResource::make($attachment->load('uploader:id,name'))->resolve($request),
            'message' => 'Attachment uploaded successfully.',
        ], 201);
    }

    public function download(
        Request $request,
        Workspace $workspace,
        RequestSubmission $requestSubmission,
        RequestAttachment $attachment,
    ): StreamedResponse {
        Gate::authorize('view', $requestSubmission);
        $storage = Storage::disk($attachment->disk);
        abort_unless($storage->exists($attachment->path), 404, 'Attachment file not found.');

        return $storage->download(
            $attachment->path,
            $this->safeDownloadName($attachment->original_name),
            ['Content-Type' => $attachment->mime_type],
        );
    }

    private function safeDownloadName(string $originalName): string
    {
        $name = basename(str_replace('\\', '/', $originalName));
        $safe = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '_', $name);

        return is_string($safe) && trim($safe) !== '' ? $safe : 'attachment';
    }
}
