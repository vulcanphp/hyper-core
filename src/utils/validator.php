<?php

namespace hyper\utils;

/**
 * Class validator
 * 
 * Validator class provides methods to validate data based on specified rules.
 * Includes validation methods for common data types and constraints.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class validator
{
    /**
     * @var array Holds any validation errors that occur.
     */
    protected array $errors = [];

    /**
     * Validates input data against specified rules.
     *
     * @param array $rules Array of validation rules where the key is the field name
     *                     and the value is an array of rules for that field.
     * @param array $inputData Array of input data to validate.
     * @return bool|array Returns validated data as an array if valid, or false if validation fails.
     */
    public function validate(array $rules, array $inputData): bool|array
    {
        $validData = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $inputData[$field] ?? null;
            $valid = true;

            foreach ($fieldRules as $rule) {
                // Parse rule name and parameters
                $ruleName = $rule;
                $ruleParams = [];
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $ruleParams] = explode(':', $rule, 2);
                    $ruleParams = explode(',', $ruleParams);
                }

                // Apply validation rule
                $valid = match ($ruleName) {
                    'required' => !empty($value) || !in_array($value, [0, true, false], true),
                    'email' => !is_null($value) ? filter_var($value, FILTER_VALIDATE_EMAIL) : true,
                    'url' => !is_null($value) ? filter_var($value, FILTER_VALIDATE_URL) : true,
                    'number' => !is_null($value) ? is_numeric($value) : true,
                    'array' => !is_null($value) ? is_array($value) : true,
                    'text' => !is_null($value) ? is_string($value) : true,
                    'min' => !is_null($value) ? strlen($value) >= (int) $ruleParams[0] : true,
                    'max' => !is_null($value) ? strlen($value) <= (int) $ruleParams[0] : true,
                    'length' => !is_null($value) ? strlen($value) == (int) $ruleParams[0] : true,
                    'equal' => !is_null($value) ? $value == ($inputData[$ruleParams[0]] ?? '') : true,
                    default => true
                };

                // Add error if rule validation fails
                if (!$valid) {
                    $this->addError($field, $ruleName, $ruleParams);
                }
            }

            // Store valid data if field passed all rules
            if ($valid) {
                $validData[$field] = $value;
            }
        }

        // Return validated data or false if there are errors
        return empty($this->errors) ? $validData : false;
    }

    /**
     * Returns all validation errors.
     *
     * @return array Associative array of field names and error messages.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns the first error message, if any.
     *
     * @return string|null First error message or null if no errors.
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? (array_values(array_values($this->errors)[0] ?? [])[0] ?? null) : null;
    }

    /** @Add helper methods for this validator object */

    /**
     * Adds an error message for a failed validation rule.
     *
     * @param string $field Field name that failed validation.
     * @param string $rule Validation rule that failed.
     * @param array $params Parameters for the rule, if any.
     */
    protected function addError(string $field, string $rule, array $params = []): void
    {
        $prettyField = $this->prettyField($field);

        // Error messages for each validation rule
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

        // Store error message
        $this->errors[$field][] = $messages[$rule] ?? "The $prettyField field has an invalid value.";
    }

    /**
     * Converts a field name into a human-readable format.
     *
     * @param string $field Field name to convert.
     * @return string Pretty field name.
     */
    protected function prettyField(string $field): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $field));
    }
}
