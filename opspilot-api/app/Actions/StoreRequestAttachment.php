<?php

namespace App\Actions;

use App\Enums\RequestActivityType;
use App\Models\RequestAttachment;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\RequestActivityRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreRequestAttachment
{
    public function __construct(private RequestActivityRecorder $activities) {}

    public function handle(RequestSubmission $submission, User $uploader, UploadedFile $file): RequestAttachment
    {
        $disk = (string) config('filesystems.attachments.disk', 'local');
        $directory = "requests/{$submission->workspace_id}/{$submission->id}";
        $path = Storage::disk($disk)->putFileAs($directory, $file, (string) Str::uuid());
        if ($path === false) {
            throw new RuntimeException('The attachment could not be stored.');
        }

        try {
            return DB::transaction(function () use ($submission, $uploader, $file, $disk, $path): RequestAttachment {
                $attachment = new RequestAttachment([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                ]);
                $attachment->workspace()->associate($submission->workspace_id);
                $attachment->requestSubmission()->associate($submission);
                $attachment->uploader()->associate($uploader);
                $attachment->save();

                $this->activities->record(
                    $submission,
                    RequestActivityType::AttachmentUploaded,
                    actor: $uploader,
                    attachment: $attachment,
                );

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
