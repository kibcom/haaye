<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param array<string, string> $rules
     */
    public function validate(array $rules): bool
    {
        foreach ($rules as $field => $ruleString) {
            $rulesList = array_filter(explode('|', $ruleString));
            $value = $this->data[$field] ?? null;

            foreach ($rulesList as $rule) {
                $name = $rule;
                $param = null;
                if (str_contains($rule, ':')) {
                    [$name, $param] = explode(':', $rule, 2);
                }

                $this->apply($field, $value, $name, $param);
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function apply(string $field, mixed $value, string $rule, ?string $param): void
    {
        $label = ucwords(str_replace('_', ' ', $field));

        if ($rule === 'required' && ($value === null || $value === '')) {
            $this->add($field, $label . ' is required.');
            return;
        }

        if (($value === null || $value === '') && $rule !== 'required') {
            return;
        }

        if ($rule === 'email' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $this->add($field, $label . ' must be a valid email address.');
        }

        if ($rule === 'min' && is_string($value) && strlen($value) < (int) $param) {
            $this->add($field, $label . ' must be at least ' . $param . ' characters.');
        }

        if ($rule === 'max' && is_string($value) && strlen($value) > (int) $param) {
            $this->add($field, $label . ' may not be greater than ' . $param . ' characters.');
        }

        if ($rule === 'in' && is_string($param)) {
            $allowed = explode(',', $param);
            if (!in_array((string) $value, $allowed, true)) {
                $this->add($field, $label . ' is invalid.');
            }
        }

        if ($rule === 'confirmed') {
            $confirmation = $this->data[$field . '_confirmation'] ?? null;
            if ((string) $value !== (string) $confirmation) {
                $this->add($field, $label . ' confirmation does not match.');
            }
        }

        if ($rule === 'date' && strtotime((string) $value) === false) {
            $this->add($field, $label . ' must be a valid date.');
        }
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
