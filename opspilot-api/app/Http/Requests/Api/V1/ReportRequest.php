<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('viewReports', $workspace) === true;
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'from' => ['required_with:to', 'date_format:Y-m-d'],
            'to' => [
                'required_with:from',
                'date_format:Y-m-d',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $from = $this->input('from');
                    if (! is_string($from) || ! is_string($value)) {
                        return;
                    }

                    try {
                        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
                        $end = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
                    } catch (Throwable) {
                        return;
                    }
                    if ($start !== false && $end !== false && $start->diffInDays($end, true) + 1 > 366) {
                        $fail('The selected date range may not exceed 366 days.');
                    }
                },
            ],
            'request_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('request_types', 'id')->where('workspace_id', $workspace?->id),
            ],
        ];
    }
}
