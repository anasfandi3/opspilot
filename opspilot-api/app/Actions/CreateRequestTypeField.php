<?php

namespace App\Actions;

use App\Enums\RequestFieldType;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRequestTypeField
{
    /** @param array{key: string, label: string, type: string, description?: ?string, is_required?: bool, config?: ?array} $data */
    public function handle(RequestType $requestType, array $data): RequestTypeField
    {
        return DB::transaction(function () use ($requestType, $data): RequestTypeField {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);

            if ($lockedRequestType->fields()->where('key', $data['key'])->exists()) {
                throw ValidationException::withMessages(['key' => 'The key has already been taken.']);
            }

            $field = new RequestTypeField($data);
            $field->forceFill([
                'key' => $data['key'],
                'type' => RequestFieldType::from($data['type']),
                'position' => ((int) $lockedRequestType->fields()->max('position')) + 1,
            ]);
            $field->requestType()->associate($lockedRequestType);
            $field->save();

            return $field;
        }, attempts: 3);
    }
}
