<?php

namespace hyper\utils;

use hyper\model;
use hyper\request;

class form
{
    private array $fields = [];

    public function __construct(
        private request $request,
        private ?model $model = null,
        array $fields = []
    ) {
        if ($model !== null && method_exists($model, 'formFields')) {
            $fields = array_merge($model->formFields(), $fields);
        }
        foreach ($fields as $field) {
            $this->add(...$field);
        }
    }

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

    public function extend(string $name, array $field): self
    {
        $this->fields[$name] = array_merge($this->fields[$name] ?? [], $field);
        return $this;
    }

    public function load(array $data): void
    {
        foreach ($this->fields as $name => $field) {
            $this->fields[$name] = array_merge(
                $field,
                ['value' => ($field['type'] == 'checkbox' ? (isset($data[$name]) && $data[$name] == 'on') : $data[$name] ?? null)]
            );
        }
    }

    public function validate(): bool
    {
        $this->load(
            $this->request->all()
        );

        $validator = new validator();
        $fields = [];
        foreach ($this->fields as $field) {
            $rules = [];
            if ($field['required']) {
                $rules[] = 'required';
            }
            $rules[] = in_array($field['type'], ['email', 'url', 'number']) ? $field['type'] : (($field['multiple'] || $field['type'] == 'file') ? 'array' : 'text');
            if ($field['min']) {
                $rules[] = 'min:' . $field['min'];
            }
            if ($field['max']) {
                $rules[] = 'max:' . $field['max'];
            }
            $fields[$field['name']] = $rules;
        }
        if (!$validator->validate($fields, $this->request->all())) {
            foreach ($validator->getErrors() as $name => $errors) {
                $this->extend($name, ['hasError' => true, 'errors' => $errors]);
            }
            return false;
        }
        return true;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getData(): array
    {
        $data = [];
        foreach ($this->fields as $field) {
            $data[$field['name']] = $field['value'];
        }
        return $data;
    }

    public function save(): int|bool
    {
        $this->model = $this->model->load(
            $this->getData()
        );
        return $this->model->save();
    }

    public function getModel(): model
    {
        return $this->model;
    }

    public function render(?string $boilerplate = null, array $class = []): string
    {
        if ($boilerplate === null) {
            $boilerplate = <<<HTML
                <div class="{groupClass}">
                    <label for="{id}" class="{labelClass}">{label}</label>
                    {field}
                    {errors}
                </div>
            HTML;
        }
        $output = [];
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
        return str_ireplace(
            ['{groupClass}', '{labelClass}', '{inputClass}', '{inputErrorClass}', '{checkboxClass}', '{checkboxErrorClass}', '{textareaClass}', '{textareaErrorClass}', '{selectClass}', '{selectErrorClass}', '{radioClass}', '{radioErrorClass}', '{errorListClass}', '{errorListItemClass}'],
            [$class['groupClass'] ?? '', $class['labelClass'] ?? '', $class['inputClass'] ?? '', $class['inputErrorClass'] ?? '', $class['checkboxClass'] ?? '', $class['checkboxErrorClass'] ?? '', $class['textareaClass'] ?? '', $class['textareaErrorClass'] ?? '', $class['selectClass'] ?? '', $class['selectErrorClass'] ?? '', $class['radioClass'] ?? '', $class['radioErrorClass'] ?? '', $class['errorListClass'] ?? '', $class['errorListItemClass'] ?? ''],
            implode("\n", $output)
        );
    }

    private function parseFieldData(array $field): array
    {
        $field['id'] = $field['id'] ?? $field['name'];
        $field['label'] = __($field['label'] ?? ucwords(str_replace(['-', '_'], ' ', $field['name'])));
        $field['placeholder'] = isset($field['placeholder']) ? __($field['placeholder']) : $field['label'];
        $field['value'] = $field['type'] == 'file' ? (($oldFile = $this->request->post('_' . $field['name'])) != null ? ($field['multiple'] ? explode(',', $oldFile) : $oldFile) : $field['value']) : $field['value'];
        return $field;
    }

    private function renderFieldHtml(array $field): string
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

    private function renderSelect(array $field): string
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

    private function renderRadio(array $field): string
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

    private function renderAttributes(array $attrs): string
    {
        $output = [];
        foreach ($attrs as $key => $value) {
            $output[] = "$key=\"$value\"";
        }
        return implode(' ', $output);
    }

    private function renderErrors(array $field): string
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
