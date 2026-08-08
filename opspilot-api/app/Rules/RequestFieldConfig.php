<?php

namespace App\Rules;

use App\Enums\RequestFieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RequestFieldConfig implements ValidationRule
{
    public function __construct(private ?RequestFieldType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->type === null || ! is_array($value)) {
            return;
        }

        match ($this->type) {
            RequestFieldType::Text, RequestFieldType::Textarea => $this->lengths($value, $fail, true),
            RequestFieldType::Email, RequestFieldType::Url => $this->lengths($value, $fail, false),
            RequestFieldType::Number, RequestFieldType::Decimal => $this->range($value, $fail),
            RequestFieldType::Select, RequestFieldType::Multiselect => $this->options($value, $fail),
            default => $this->rejectKeys($value, [], $fail),
        };
    }

    private function lengths(array $config, Closure $fail, bool $allowMinimum): void
    {
        $allowed = $allowMinimum ? ['min_length', 'max_length'] : ['max_length'];
        if (! $this->rejectKeys($config, $allowed, $fail)) {
            return;
        }
        if (isset($config['min_length']) && (! is_int($config['min_length']) || $config['min_length'] < 0)) {
            $fail('The :attribute min_length must be a non-negative integer.');
        }
        if (isset($config['max_length']) && (! is_int($config['max_length']) || $config['max_length'] < 1)) {
            $fail('The :attribute max_length must be a positive integer.');
        }
        if (isset($config['min_length'], $config['max_length']) && $config['min_length'] > $config['max_length']) {
            $fail('The :attribute min_length may not exceed max_length.');
        }
    }

    private function range(array $config, Closure $fail): void
    {
        if (! $this->rejectKeys($config, ['min', 'max'], $fail)) {
            return;
        }
        foreach (['min', 'max'] as $key) {
            if (array_key_exists($key, $config) && ! is_numeric($config[$key])) {
                $fail("The :attribute {$key} must be numeric.");
            }
        }
        if (isset($config['min'], $config['max']) && is_numeric($config['min']) && is_numeric($config['max'])
            && (float) $config['min'] > (float) $config['max']) {
            $fail('The :attribute min may not exceed max.');
        }
    }

    private function options(array $config, Closure $fail): void
    {
        if (! $this->rejectKeys($config, ['options'], $fail)) {
            return;
        }
        if (! isset($config['options']) || ! is_array($config['options']) || $config['options'] === []) {
            $fail('The :attribute options must be a non-empty array.');

            return;
        }

        $values = [];
        foreach ($config['options'] as $option) {
            if (! is_array($option) || array_diff(array_keys($option), ['value', 'label']) !== []
                || array_diff(['value', 'label'], array_keys($option)) !== []) {
                $fail('Each :attribute option must contain only value and label.');

                return;
            }
            if (! is_string($option['value']) || trim($option['value']) === ''
                || ! is_string($option['label']) || trim($option['label']) === '') {
                $fail('Each :attribute option value and label must be non-empty strings.');

                return;
            }
            if (in_array($option['value'], $values, true)) {
                $fail('The :attribute option values must be unique.');

                return;
            }
            $values[] = $option['value'];
        }
    }

    /** @param list<string> $allowed */
    private function rejectKeys(array $config, array $allowed, Closure $fail): bool
    {
        if (array_diff(array_keys($config), $allowed) !== []) {
            $fail('The :attribute contains unsupported keys.');

            return false;
        }

        return true;
    }
}
