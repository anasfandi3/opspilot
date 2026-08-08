<?php

namespace App\Actions;

use App\Models\RequestType;
use Illuminate\Support\Facades\DB;

class DeleteRequestType
{
    public function handle(RequestType $requestType): void
    {
        DB::transaction(function () use ($requestType): void {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);

            // Remove conditions through their workflow tree before request type fields.
            $lockedRequestType->workflows()->delete();
            $lockedRequestType->delete();
        }, attempts: 3);
    }
}
