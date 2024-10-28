<?php

namespace hyper\utils;

use hyper\model;
use hyper\request;

/**
 * Class form
 * 
 * This class is used to build, validate, and render forms dynamically.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class form
{
    /**
     * @var array $fields Stores form field configurations.
     */
    protected array $fields = [];

    /**
     * Constructor
     * 
     * @param request $request The request instance for form data handling.
     * @param model|null $model Optional model instance to integrate with form.
     * @param array $fields Initial set of fields for the form.
     */
    public function __construct(
        protected request $request,
        protected ?model $model = null,
        array $fields = []
    ) {
        if ($model !== null && method_exists($model, 'formFields')) {
            $fields = array_merge($model->formFields(), $fields);
        }

        foreach ($fields as $field) {
            $this->add(...$field);
        }
    }

    /**
     * Adds a field to the form.
     * 
     * @param string $type Field type (e.g., text, checkbox, etc.)
     * @param string $name Field name.
     * @param bool $required Whether the field is required.
     * @param int|null $min Minimum value/length.
     * @param int|null $max Maximum value/length.
     * @param null|array|int|bool|string $value Default value for the field.
     * @param array $options Options for select, radio, etc.
     * @param string|null $placeholder Placeholder text.
     * @param string|null $label Field label.
     * @param bool $multiple Whether multiple values are allowed.
     * @param array $attrs Additional HTML attributes.
     * @param string|null $id HTML ID attribute.
     * @param bool $hasError Indicates if the field has validation errors.
     * @param array $errors Validation error messages.
     * 
     * @return self
     */
    public function add(
        string $type,
        string $name,
        bool $required = false,
        ?int $min = null,
        ?int $max = null,
        null|array|int|bool|string $value = null,
        array $options = [],
        ?string $placeholder = null,
        ?string $label = null,
        bool $multiple = false,
        array $attrs = [],
        ?string $id = null,
        bool $hasError = false,
        array $errors = [],
    ): self {
        $this->fields[$name] = get_defined_vars();
        return $this;
    }

    /**
     * Extends an existing field with additional configurations.
     * 
     * @param string $name Field name to extend.
     * @param array $field Additional field configurations.
     * 
     * @return self
     */
    public function extend(string $name, array $field): self
    {
        $this->fields[$name] = array_merge($this->fields[$name] ?? [], $field);
        return $this;
    }

    /**
     * Loads data into form fields.
     * 
     * @param array $data Data to populate form fields.
     */
    public function load(array $data): void
    {
        foreach ($this->fields as $name => $field) {
            $this->fields[$name] = array_merge(
                $field,
                ['value' => ($field['type'] == 'checkbox' ? (isset($data[$name]) && $data[$name] == 'on') : $data[$name] ?? null)]
            );
        }
    }

    /**
     * Validates form data.
     * 
     * @return bool True if validation passes, false otherwise.
     */
    public function validate(): bool
    {
        // Load input values from request into this form.
        $this->load(
            $this->request->all()
        );

        // Create a validator instance.
        $validator = new validator();
        $fields = [];

        // Add validator ruled dynamically.
        foreach ($this->fields as $field) {
            $rules = [];

            // Add required rule.
            if ($field['required']) {
                $rules[] = 'required';
            }

            // Add eamil, url, and number rule.
            $rules[] = in_array($field['type'], ['email', 'url', 'number']) ?
                $field['type'] : (($field['multiple'] || $field['type'] == 'file') ? 'array' : 'text');

            // Add minimum input length rule.
            if ($field['min']) {
                $rules[] = 'min:' . $field['min'];
            }

            // Add maximum input length rule.
            if ($field['max']) {
                $rules[] = 'max:' . $field['max'];
            }

            // Push this rule into validator.
            $fields[$field['name']] = $rules;
        }

        // Validate this form and add errors into input items. 
        if (!$validator->validate($fields, $this->request->all())) {
            foreach ($validator->getErrors() as $name => $errors) {
                $this->extend($name, ['hasError' => true, 'errors' => $errors]);
            }

            // Return fails and render the form with error, when validation is failed.
            return false;
        }

        // Returns true when this for is passed with input validation.
        return true;
    }

    /**
     * Retrieves all form fields.
     * 
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Retrieves data from the form.
     * 
     * @return array
     */
    public function getData(): array
    {
        // Holds input items values.
        $data = [];

        // Extract all values from input items.
        foreach ($this->fields as $field) {
            $data[$field['name']] = $field['value'];
        }

        // Return key=>value array of from inputs.
        return $data;
    }

    /**
     * Saves the form data using the associated model.
     * 
     * @return int|bool
     */
    public function save(): int|bool
    {
        // Dynamically load a model from input values.
        $this->model = $this->model->load(
            $this->getData()
        );

        // The ID of the saved record or false on failure.
        return $this->model->save();
    }

    /**
     * Returns the model associated with the form.
     * 
     * @return model
     */
    public function getModel(): model
    {
        return $this->model;
    }

    /**
     * Renders the form as HTML.
     * 
     * @param string|null $boilerplate Custom HTML template.
     * @param array $class CSS classes for form components.
     * 
     * @return string Rendered HTML.
     */
    public function render(?string $boilerplate = null, array $class = []): string
    {
        // Set a default boilerplate to render input items.
        if ($boilerplate === null) {
            $boilerplate = <<<HTML
                <div class="{groupClass}">
                    <label for="{id}" class="{labelClass}">{label}</label>
                    {field}
                    {errors}
                </div>
            HTML;
        }

        // Holds all input elemets in this arrau.
        $output = [];

        // Add input html element for each form element.
        foreach ($this->fields as $field) {
            $field = $this->parseFieldData($field);
            $output[] = str_ireplace(
                ['{id}', '{label}', '{field}', '{errors}'],
                [
                    $field['id'],
                    $field['label'],
                    $this->renderFieldHtml($field),
                    $this->renderErrors($field),
                ],
                $field['type'] == 'hidden' ? '{field}' : $boilerplate
            );
        }

        // Dynamically add input element classe names, and returns as string.
        return str_ireplace(
            [
                '{groupClass}', '{labelClass}', '{inputClass}', '{inputErrorClass}', '{checkboxClass}',
                '{checkboxErrorClass}', '{textareaClass}', '{textareaErrorClass}', '{selectClass}',
                '{selectErrorClass}', '{radioClass}', '{radioErrorClass}', '{errorListClass}', '{errorListItemClass}'
            ],
            [
                $class['groupClass'] ?? '', $class['labelClass'] ?? '', $class['inputClass'] ?? '',
                $class['inputErrorClass'] ?? '', $class['checkboxClass'] ?? '', $class['checkboxErrorClass'] ?? '',
                $class['textareaClass'] ?? '', $class['textareaErrorClass'] ?? '', $class['selectClass'] ?? '',
                $class['selectErrorClass'] ?? '', $class['radioClass'] ?? '', $class['radioErrorClass'] ?? '',
                $class['errorListClass'] ?? '', $class['errorListItemClass'] ?? ''
            ],
            implode("\n", $output)
        );
    }

    /** @Add helper methods for this form object */

    /**
     * Parses field data for rendering.
     * 
     * @param array $field Field configuration.
     * 
     * @return array Parsed field data.
     */
    protected function parseFieldData(array $field): array
    {
        $field['id'] = $field['id'] ?? $field['name'];
        $field['label'] = __($field['label'] ?? ucwords(str_replace(['-', '_'], ' ', $field['name'])));
        $field['placeholder'] = isset($field['placeholder']) ? __($field['placeholder']) : $field['label'];

        $field['value'] = $field['type'] == 'file' ? (
            ($oldFile = $this->request->post('_' . $field['name'])) != null ?
            ($field['multiple'] ? explode(',', $oldFile) : $oldFile)
            : $field['value']
        ) : $field['value'];

        return $field;
    }

    /**
     * Generates HTML for the field based on its type.
     * 
     * @param array $field Field configuration.
     * 
     * @return string Rendered field HTML.
     */
    protected function renderFieldHtml(array $field): string
    {
        $attrs = $this->renderAttributes($field['attrs']);
        $required = $field['required'] ? 'required' : '';

        return match ($field['type']) {
            'text', 'hidden', 'number', 'color', 'password', 'range', 'search', 'datetime-local', 'date', 'time', 'email' => "<input type=\"{$field['type']}\" name=\"{$field['name']}\" id=\"{$field['id']}\" value=\"{$field['value']}\" placeholder=\"{$field['placeholder']}\" $attrs class=\"{inputClass} " . ($field['hasError'] ? '{inputErrorClass}' : '') . "\" $required>",
            'file' => "<input type=\"file\" name=\"{$field['name']}" . ($field['multiple'] ? '[]' : '') . "\" id=\"{$field['id']}\" $attrs class=\"{inputClass} " . ($field['hasError'] ? '{inputErrorClass}' : '') . "\" " . ($field['multiple'] ? 'multiple' : '') . " " . (!isset($field['value']) ? $required : '') . "> " . (isset($field['value']) ?  (is_array($field['value']) ? count($field['value']) . ' files uploaded' : '') : '') . (isset($field['value']) ? '<input type="hidden" name="_' . $field['name'] . '" value="' .  implode(',', (array) $field['value']) . '">' . (isset($field['value']) ? ('<p style="font-size:12px;margin-top: 2px;">' . implode('<br/>', array_map(fn ($file) => '<a href="' . media_url($file) . '" target="_blank">' . $file . '</a>', (array) $field['value'])) . '</p>') : '') : ''),
            'checkbox' => "<label class=\"{checkboxClass} " . ($field['hasError'] ? '{checkboxErrorClass}' : '') . "\"><input type=\"checkbox\" name=\"{$field['name']}\" id=\"{$field['id']}\" value=\"on\" " . ($field['value'] ? 'checked' : '') . " $attrs $required> " . $field['placeholder'] . "</label>",
            'radio' => $this->renderRadio($field),
            'select' => $this->renderSelect($field),
            'textarea' => "<textarea name=\"{$field['name']}\" id=\"{$field['id']}\" placeholder=\"{$field['placeholder']}\" $attrs class=\"{textareaClass} " . ($field['hasError'] ? '{textareaErrorClass}' : '') . "\" $required>{$field['value']}</textarea>",
            default => '',
        };
    }

    /**
     * Renders a select input field.
     * 
     * @param array $field Field configuration.
     * 
     * @return string Rendered select HTML.
     */
    protected function renderSelect(array $field): string
    {
        $attrs = $this->renderAttributes($field['attrs']);
        $required = $field['required'] ? 'required' : '';
        $options = '';

        foreach ($field['options'] as $key => $val) {
            $selected = isset($field['value']) && ($field['multiple'] ? in_array($key, $field['value']) : $field['value'] == $key) ? 'selected' : '';
            $options .= "<option value=\"$key\" $selected>$val</option>";
        }

        return "<select name=\"{$field['name']}" . ($field['multiple'] ? '[]' : '') . "\" id=\"{$field['id']}\" $required $attrs class=\"{selectClass} " . ($field['hasError'] ? '{selectErrorClass}' : '') . "\" " . ($field['multiple'] ? 'multiple' : '') . ">$options</select>";
    }

    /**
     * Renders radio buttons for a field.
     * 
     * @param array $field Field configuration.
     * 
     * @return string Rendered radio HTML.
     */
    protected function renderRadio(array $field): string
    {
        $attrs = $this->renderAttributes($field['attrs']);
        $required = $field['required'] ? 'required' : '';
        $radios = '';

        foreach ($field['options'] as $key => $val) {
            $checked = $field['value'] == $key ? 'checked' : '';
            $radios .= "<label class=\"{radioClass} " . ($field['hasError'] ? '{radioErrorClass}' : '') . "\"><input type=\"radio\" name=\"{$field['name']}\" value=\"$key\" $checked $required $attrs> $val</label>";
        }

        return $radios;
    }

    /**
     * Converts an array of attributes to a string.
     * 
     * @param array $attrs HTML attributes.
     * 
     * @return string Attributes as a string.
     */
    protected function renderAttributes(array $attrs): string
    {
        $output = [];

        foreach ($attrs as $key => $value) {
            $output[] = "$key=\"$value\"";
        }

        return implode(' ', $output);
    }

    /**
     * Renders error messages for a field.
     * 
     * @param array $field Field configuration.
     * 
     * @return string Rendered error messages HTML.
     */
    protected function renderErrors(array $field): string
    {
        if (empty($field['errors'])) {
            return '';
        }

        $errorMessages = array_map(fn ($error) => "<li class=\"{errorListItemClass}\">" . __($error) . "</li>", $field['errors']);
        $errorMessages = implode("\n", $errorMessages);

        return <<<HTML
            <ul class="{errorListClass}">
                {$errorMessages}
            </ul>
        HTML;
    }
}
