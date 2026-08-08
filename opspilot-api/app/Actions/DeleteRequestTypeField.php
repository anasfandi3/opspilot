<?php

namespace App\Actions;

use App\Models\RequestTypeField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRequestTypeField
{
    public function handle(RequestTypeField $field): void
    {
        DB::transaction(function () use ($field): void {
            $locked = RequestTypeField::query()->lockForUpdate()->findOrFail($field->id);
            if ($locked->workflowConditions()->exists()) {
                throw ValidationException::withMessages(['field' => 'A field referenced by a workflow condition cannot be deleted.']);
            }
            $locked->delete();
        }, attempts: 3);
    }
}
