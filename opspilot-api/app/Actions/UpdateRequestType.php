<?php

namespace App\Actions;

use App\Models\RequestType;
use Illuminate\Support\Facades\DB;

class UpdateRequestType
{
    /** @param array<string, mixed> $data */
    public function handle(RequestType $requestType, array $data): RequestType
    {
        return DB::transaction(function () use ($requestType, $data): RequestType {
            $locked = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);
            $locked->update($data);

            return $locked->refresh();
        }, attempts: 3);
    }
}
