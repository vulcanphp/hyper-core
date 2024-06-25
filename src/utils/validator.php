<?php

namespace hyper\utils;

use hyper\request;

class validator
{
    private array $errors = [];

    public function __construct(private request $request)
    {
    }

    public function validate(array $rules): bool|array
    {
        $data = $this->request->all();
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
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
                    default => true
                };
                if (!$valid) {
                    $this->addError($field, $ruleName, $ruleParams);
                }
            }
        }
        return empty($this->errors) ? $data : false;
    }

    private function addError(string $field, string $rule, array $params = []): void
    {
        $prettyField = ucwords(str_replace(['-', '_'], ' ', $field));
        $messages = [
            'required' => sprintf("The %s field is required.", $prettyField),
            'email' => sprintf("The %s field must be a valid email address.", $prettyField),
            'url' => sprintf("The %s field must be a valid URL.", $prettyField),
            'number' => sprintf("The %s field must be a number.", $prettyField),
            'array' => sprintf("The %s field must be an array.", $prettyField),
            'text' => sprintf("The %s field must be a text.", $prettyField),
            'min' => sprintf("The %s field must be at least %s characters long.", $prettyField, $params[0] ?? 0),
            'max' => sprintf("The %s field must not exceed %s characters.", $prettyField, $params[0] ?? 0)
        ];
        $this->errors[$field][] = $messages[$rule] ?? "The $prettyField field has an invalid value.";
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
