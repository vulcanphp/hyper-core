<?php

namespace hyper\helpers;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

trait form
{
    protected function form(): array
    {
        return [];
    }

    public function formFields(): array
    {
        $fields = [];
        $fieldSetup = $this->form();
        $uploads = collect(method_exists($this, 'uploads') ? $this->uploads() : []);
        foreach ($this->extractModelProperties() as $name => $field) {
            $type = 'text';
            $multiple = false;

            if (isset($fieldSetup[$name]['ignore']) && $fieldSetup[$name]['ignore']) {
                continue;
            } elseif ($name == 'id') {
                if (isset($field['default']) || request()->all(['id'])['id'] != null) {
                    $type = 'hidden';
                } else {
                    continue;
                }
            } elseif (in_array('int', $field['type'])) {
                $type = 'number';
            } elseif (in_array('bool', $field['type'])) {
                $type = 'checkbox';
            } elseif ($uploads->pluck('name')->in($name)) {
                $upload = $uploads->find(fn ($upload) => $upload['name'] == $name);
                $type = 'file';
                $multiple = isset($upload['multiple']) && $upload['multiple'];
            }
            $fields[] = array_merge([
                'type' => $type,
                'name' => $name,
                'multiple' => $multiple,
                'required' => !in_array('null', $field['type']),
                'value' => $field['default'],
            ], $fieldSetup[$name] ?? []);
        }
        if (method_exists($this, 'extractOrmFields')) {
            $fields = array_merge($fields, $this->extractOrmFields());
        }
        return $fields;
    }

    private function extractModelProperties(): array
    {
        $reflector = new ReflectionClass($this);
        $result = [];
        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $type = 'mixed';
            $default = $this->{$name} ?? null;
            if ($property->hasType()) {
                $type = $property->getType();
                if ($type instanceof ReflectionUnionType) {
                    $type = array_map(fn ($t) => $t->getName(), $type->getTypes());
                } elseif ($type instanceof ReflectionNamedType) {
                    $type = [$type->getName(), ($type->allowsNull() ? 'null' : 'notNull')];
                } else {
                    $type = 'mixed';
                }
            }
            $result[$name] = [
                'type' => (array) $type,
                'default' => $default,
            ];
        }
        return $result;
    }
}
