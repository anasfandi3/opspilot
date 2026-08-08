<?php

namespace App\Support;

use App\Enums\RequestFieldType;
use App\Models\RequestType;
use App\Models\RequestTypeField;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestPayloadValidator
{
    /** @param array<string, mixed> $payload */
    public function validateDraft(RequestType $requestType, array $payload): void
    {
        $this->validate($requestType, $payload, false);
    }

    /** @param array<string, mixed> $payload */
    public function validateSubmission(RequestType $requestType, array $payload): void
    {
        $this->validate($requestType, $payload, true);
    }

    /** @param array<string, mixed> $payload */
    private function validate(RequestType $requestType, array $payload, bool $submitting): void
    {
        $fields = $requestType->relationLoaded('fields') ? $requestType->fields : $requestType->fields()->get();
        $fieldsByKey = $fields->keyBy('key');
        $errors = [];

        foreach (array_keys($payload) as $key) {
            if (! is_string($key) || ! $fieldsByKey->has($key)) {
                $errors['payload.'.(string) $key][] = 'The payload contains an unknown field.';
            }
        }

        foreach ($fields as $field) {
            $present = array_key_exists($field->key, $payload);
            $value = $present ? $payload[$field->key] : null;
            $attribute = 'payload.'.$field->key;

            if ($submitting && $field->is_required && (! $present || $this->missingRequiredValue($field, $value))) {
                $errors[$attribute][] = 'The field is required.';

                continue;
            }
            if (! $present || $value === null || (! $submitting && $this->isIncompleteDraftValue($value))) {
                continue;
            }

            $message = $this->validationMessage($field, $value);
            if ($message !== null) {
                $errors[$attribute][] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function missingRequiredValue(RequestTypeField $field, mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return match ($field->type) {
            RequestFieldType::Text, RequestFieldType::Textarea, RequestFieldType::Email, RequestFieldType::Url => $value === '',
            RequestFieldType::Multiselect => $value === [],
            default => false,
        };
    }

    private function isIncompleteDraftValue(mixed $value): bool
    {
        return $value === '' || $value === [];
    }

    private function validationMessage(RequestTypeField $field, mixed $value): ?string
    {
        return match ($field->type) {
            RequestFieldType::Text, RequestFieldType::Textarea => $this->validateText($field, $value),
            RequestFieldType::Number => $this->validateNumber($field, $value),
            RequestFieldType::Decimal => $this->validateDecimal($field, $value),
            RequestFieldType::Boolean => is_bool($value) ? null : 'The value must be a boolean.',
            RequestFieldType::Date => $this->validateDate($value),
            RequestFieldType::Datetime => $this->validateDatetime($value),
            RequestFieldType::Select => $this->validateSelect($field, $value),
            RequestFieldType::Multiselect => $this->validateMultiselect($field, $value),
            RequestFieldType::Email => $this->validateEmail($field, $value),
            RequestFieldType::Url => $this->validateUrl($field, $value),
        };
    }

    private function validateText(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'The value must be a string.';
        }

        $length = Str::length($value);
        if (isset($field->config['min_length']) && $length < $field->config['min_length']) {
            return 'The value is shorter than the configured minimum length.';
        }
        if (isset($field->config['max_length']) && $length > $field->config['max_length']) {
            return 'The value exceeds the configured maximum length.';
        }

        return null;
    }

    private function validateNumber(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_int($value)) {
            return 'The value must be an integer.';
        }

        return $this->validateRange($field, $value);
    }

    private function validateDecimal(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_int($value) && ! is_float($value)) {
            return 'The value must be a number.';
        }

        return $this->validateRange($field, $value);
    }

    private function validateRange(RequestTypeField $field, int|float $value): ?string
    {
        if (isset($field->config['min']) && $value < $field->config['min']) {
            return 'The value is below the configured minimum.';
        }
        if (isset($field->config['max']) && $value > $field->config['max']) {
            return 'The value exceeds the configured maximum.';
        }

        return null;
    }

    private function validateDate(mixed $value): ?string
    {
        $valid = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
        if (! $valid || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return 'The value must be a valid date in YYYY-MM-DD format.';
        }

        return null;
    }

    private function validateDatetime(mixed $value): ?string
    {
        $datetime = is_string($value) ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $value) : false;

        return $datetime && $datetime->format('Y-m-d\TH:i:sP') === $value
            ? null
            : 'The value must be a valid ISO-8601 datetime.';
    }

    private function validateSelect(RequestTypeField $field, mixed $value): ?string
    {
        return is_string($value) && in_array($value, $this->optionValues($field), true)
            ? null
            : 'The value must be one of the configured options.';
    }

    private function validateMultiselect(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_array($value) || ! array_is_list($value) || collect($value)->contains(fn (mixed $item): bool => ! is_string($item))) {
            return 'The value must be an array of strings.';
        }
        if (count($value) !== count(array_unique($value))) {
            return 'The selected values must be unique.';
        }
        if (collect($value)->contains(fn (string $item): bool => ! in_array($item, $this->optionValues($field), true))) {
            return 'Every selected value must be a configured option.';
        }

        return null;
    }

    private function validateEmail(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'The value must be a valid email address.';
        }

        return $this->validateMaximumLength($field, $value);
    }

    private function validateUrl(RequestTypeField $field, mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return 'The value must be a valid URL.';
        }

        return $this->validateMaximumLength($field, $value);
    }

    private function validateMaximumLength(RequestTypeField $field, string $value): ?string
    {
        return isset($field->config['max_length']) && Str::length($value) > $field->config['max_length']
            ? 'The value exceeds the configured maximum length.'
            : null;
    }

    /** @return list<string> */
    private function optionValues(RequestTypeField $field): array
    {
        return collect($field->config['options'] ?? [])->pluck('value')->all();
    }
}
