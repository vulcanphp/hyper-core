<?php

namespace hyper\utils;

class validator
{
    private array $errors = [];

    public function validate(array $rules, array $inputData): bool|array
    {
        $validData = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $inputData[$field] ?? null;
            $valid = true;
            foreach ($fieldRules as $rule) {
                $ruleName = $rule;
                $ruleParams = [];
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $ruleParams] = explode(':', $rule, 2);
                    $ruleParams = explode(',', $ruleParams);
                }
                $valid = match ($ruleName) {
                    'required' => !empty($value),
                    'email' => !is_null($value) ? filter_var($value, FILTER_VALIDATE_EMAIL) : true,
                    'url' => !is_null($value) ? filter_var($value, FILTER_VALIDATE_URL) : true,
                    'number' => !is_null($value) ? is_numeric($value) : true,
                    'array' => !is_null($value) ? is_array($value) : true,
                    'text' => !is_null($value) ? is_string($value) : true,
                    'min' => !is_null($value) ? strlen($value) >= (int)$ruleParams[0] : true,
                    'max' => !is_null($value) ? strlen($value) <= (int)$ruleParams[0] : true,
                    'length' => !is_null($value) ? strlen($value) == (int)$ruleParams[0] : true,
                    'equal' => !is_null($value) ? $value == ($inputData[$ruleParams[0]] ?? '') : true,
                    default => true
                };
                if (!$valid) {
                    $this->addError($field, $ruleName, $ruleParams);
                }
            }
            if ($valid) {
                $validData[$field] = $value;
            }
        }
        return empty($this->errors) ? $validData : false;
    }

    private function addError(string $field, string $rule, array $params = []): void
    {
        $prettyField = $this->prettyField($field);
        $messages = [
            'required' => sprintf("The %s field is required.", $prettyField),
            'email' => sprintf("The %s field must be a valid email address.", $prettyField),
            'url' => sprintf("The %s field must be a valid URL.", $prettyField),
            'number' => sprintf("The %s field must be a number.", $prettyField),
            'array' => sprintf("The %s field must be an array.", $prettyField),
            'text' => sprintf("The %s field must be a text.", $prettyField),
            'min' => sprintf("The %s field must be at least %s characters long.", $prettyField, $params[0] ?? 0),
            'max' => sprintf("The %s field must not exceed %s characters.", $prettyField, $params[0] ?? 0),
            'length' => sprintf("The %s field must be %s characters.", $prettyField, $params[0] ?? 0),
            'equal' => sprintf("The %s field must be equal to %s field.", $prettyField, $this->prettyField($params[0] ?? '')),
        ];
        $this->errors[$field][] = $messages[$rule] ?? "The $prettyField field has an invalid value.";
    }

    private function prettyField(string $field): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $field));
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? (array_values(array_values($this->errors)[0] ?? [])[0] ?? null) : null;
    }
}
