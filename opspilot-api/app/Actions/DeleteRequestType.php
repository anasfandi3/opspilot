<?php

namespace App\Actions;

use App\Models\RequestType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRequestType
{
    public function handle(RequestType $requestType): void
    {
        DB::transaction(function () use ($requestType): void {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);
            if ($lockedRequestType->requestSubmissions()->exists()) {
                throw ValidationException::withMessages([
                    'request_type' => 'A request type with request history cannot be deleted.',
                ]);
            }

            // Remove conditions through their workflow tree before request type fields.
            $lockedRequestType->workflows()->delete();
            $lockedRequestType->delete();
        }, attempts: 3);
    }
}
