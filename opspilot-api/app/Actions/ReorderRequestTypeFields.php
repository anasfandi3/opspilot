<?php

namespace App\Actions;

use App\Models\RequestType;
use App\Models\RequestTypeField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderRequestTypeFields
{
    /** @param list<int> $fieldIds */
    public function handle(RequestType $requestType, array $fieldIds): void
    {
        DB::transaction(function () use ($requestType, $fieldIds): void {
            $lockedRequestType = RequestType::query()->lockForUpdate()->findOrFail($requestType->id);
            $fields = $lockedRequestType->fields()->reorder()->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $currentIds = $fields->keys()->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $submittedIds = collect($fieldIds)->sort()->values()->all();
            if ($currentIds !== $submittedIds || count($fieldIds) !== count(array_unique($fieldIds))) {
                throw ValidationException::withMessages(['field_ids' => 'The field_ids must contain the complete unique field set.']);
            }

            foreach ($fieldIds as $index => $fieldId) {
                /** @var RequestTypeField $field */
                $field = $fields->get($fieldId);
                $field->forceFill(['position' => $index + 1])->save();
            }
        }, attempts: 3);
    }
}
