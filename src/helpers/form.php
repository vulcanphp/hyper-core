<?php

namespace hyper\helpers;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Trait form
 * 
 * Provides utility methods to generate form field data from model properties.
 * 
 * @package hyper\helpers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
trait form
{
    /**
     * Specifies the form setup for the implementing class.
     * 
     * Override this method in the class using the trait to customize form setup.
     * 
     * @return array An array of form field configurations.
     */
    protected function form(): array
    {
        return [];
    }

    /**
     * Generates an array of form fields based on class properties and configurations.
     * 
     * This method reflects on the class properties, determines the field types, and
     * merges any custom field settings provided by the `form` method.
     * 
     * @return array An array of associative arrays, each representing a form field.
     */
    public function formFields(): array
    {
        $fields = [];
        $fieldSetup = $this->form();

        // Extract Upload fields of enabled by model.
        $uploads = collect(method_exists($this, 'uploads') ? $this->uploads() : []);

        // Extract each field dynamically from model.
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

            // Add new field item.
            $fields[] = array_merge([
                'type' => $type,
                'name' => $name,
                'multiple' => $multiple,
                'required' => !in_array('null', $field['type']),
                'value' => $field['default'],
            ], $fieldSetup[$name] ?? []);
        }

        // Extract ORM fields of enabled from model.
        if (method_exists($this, 'extractOrmFields')) {
            $fields = array_merge($fields, $this->extractOrmFields());
        }

        return $fields;
    }

    /**
     * Extracts public properties of the class and determines their types and default values.
     * 
     * Uses reflection to analyze class properties, identify their types (including union types),
     * and retrieve their default values.
     * 
     * @return array An associative array of properties, with keys as property names and values 
     *               containing 'type' and 'default' keys.
     */
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
