<?php

namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->runValidation();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    private function runValidation(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;

            foreach (explode('|', $ruleString) as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $isEmpty = $value === null || $value === '';

        switch ($name) {
            case 'required':
                if ($isEmpty) {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'email':
                if (!$isEmpty && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'numeric':
                if (!$isEmpty && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be numeric.");
                }
                break;

            case 'integer':
                if (!$isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;

            case 'min':
                if (!$isEmpty && mb_strlen((string) $value) < (int) $param) {
                    $this->addError($field, "The {$field} must be at least {$param} characters.");
                }
                break;

            case 'max':
                if (!$isEmpty && mb_strlen((string) $value) > (int) $param) {
                    $this->addError($field, "The {$field} must not exceed {$param} characters.");
                }
                break;

            case 'in':
                $allowed = explode(',', $param ?? '');
                if (!$isEmpty && !in_array($value, $allowed, true)) {
                    $this->addError($field, "The selected {$field} is invalid.");
                }
                break;

            case 'confirmed':
                $confirmField = "{$field}_confirmation";
                if (($this->data[$confirmField] ?? null) !== $value) {
                    $this->addError($field, "The {$field} confirmation does not match.");
                }
                break;

            case 'date':
                if (!$isEmpty && strtotime((string) $value) === false) {
                    $this->addError($field, "The {$field} must be a valid date.");
                }
                break;

            case 'phone':
                $digits = preg_replace('/\D/', '', (string) $value);
                if (!$isEmpty && !preg_match('/^[0-9]{10,15}$/', $digits)) {
                    $this->addError($field, "The {$field} must be a valid phone number.");
                }
                break;

            case 'unique':
                [$table, $column] = array_pad(explode(',', $param ?? ''), 2, null);
                $column = $column ?? $field;

                if (!$isEmpty && $table) {
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT 1 FROM {$table} WHERE {$column} = :value LIMIT 1");
                    $stmt->execute(['value' => $value]);
                    if ($stmt->fetch()) {
                        $this->addError($field, "The {$field} has already been taken.");
                    }
                }
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
