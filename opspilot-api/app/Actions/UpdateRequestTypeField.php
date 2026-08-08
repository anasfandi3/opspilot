<?php

namespace App\Actions;

use App\Models\RequestType;
use App\Models\RequestTypeField;
use App\Support\WorkflowDefinitionValidator;
use Illuminate\Support\Facades\DB;

class UpdateRequestTypeField
{
    public function __construct(private WorkflowDefinitionValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(RequestTypeField $field, array $data): RequestTypeField
    {
        return DB::transaction(function () use ($field, $data): RequestTypeField {
            RequestType::query()->lockForUpdate()->findOrFail($field->request_type_id);
            $locked = RequestTypeField::query()->lockForUpdate()->findOrFail($field->id);
            if (array_key_exists('config', $data)) {
                $this->validator->validateFieldConfig($locked, $data['config']);
            }
            $locked->update($data);

            return $locked->refresh();
        }, attempts: 3);
    }
}
