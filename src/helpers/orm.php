<?php

namespace hyper\helpers;

use hyper\application;
use hyper\query;
use PDO;
use RuntimeException;

/**
 * Trait orm
 * 
 * Provides functionality for handling object-relational mapping (ORM) in a PHP application.
 * It includes methods for managing relationships between models, such as one-to-one, one-to-many,
 * and many-to-many, with support for lazy loading and eager loading of related data.
 * 
 * @package hyper\helpers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
trait orm
{
    /**
     * @var array $orm
     * Holds the loaded ORM data for the current instance.
     */
    protected array $orm;

    /**
     * Method to define ORM configurations for the model.
     * Should be overridden in the implementing class to specify the ORM relationships.
     * 
     * @return array
     */
    protected function orm(): array
    {
        return [];
    }

    /**
     * Allows for eager loading of related ORM data by specifying the relationships to load.
     * 
     * @param array|string $orm The relationships to load, or '*' to load all configured relationships.
     * @param array $data The data to use for loading the initial model instance.
     * @return query The query object with attached mappers for handling the related data.
     * 
     * @throws RuntimeException If the specified relationship is not defined in the ORM configuration.
     */
    public static function with(array|string $orm = '*', array $data = []): query
    {
        $model = static::load($data);
        $query = $model->get();
        $registeredOrm = $model->orm();
        if ($orm === '*') {
            $orm = array_keys($registeredOrm);
        }
        foreach ((array) $orm as $with) {
            $config = $registeredOrm[$with] ?? false;
            if (!$config) {
                throw new RuntimeException("Orm({$with}) does not specified in: " . $model::class);
            }
            $query->addMapper(function ($data) use ($model, $config, $with) {
                if (!(isset($data[0]) && is_object($data[0]))) {
                    return $data;
                }
                return $model->__handleOrm($data, $config, $with);
            });
        }
        return $query;
    }

    /**
     * Retrieve the registered ORM configurations for the model.
     * 
     * @return array
     */
    public function getRegisteredOrm(): array
    {
        $registeredOrm = $this->orm();
        return $registeredOrm;
    }

    /**
     * Magic getter method to load and return ORM data on demand (lazy loading).
     * 
     * @param string $name The name of the ORM relationship to load.
     * @return mixed|null The related data if available, or null if not found.
     * 
     * @throws RuntimeException If lazy loading is disabled for the requested relationship.
     */

    public function __get($name)
    {
        if (isset($this->orm[$name])) {
            return $this->orm[$name];
        }
        // load lazy orm data
        $config = $this->orm()[$name] ?? false;
        if ($config) {
            if (isset($config['lazy']) && !$config['lazy']) {
                throw new RuntimeException("Lazy load has been disabled for Orm({$name}), " . static::class);
            }
            $this->__handleOrm([$this], $config, $name);
            return $this->orm[$name];
        }
        return null;
    }

    /**
     * Handles loading of ORM data based on the specified configuration.
     * Supports different relationship types: 'one', 'many', and 'many-x'.
     * 
     * @param array $data The main data set to attach related data to.
     * @param array $config The configuration for the ORM relationship.
     * @param string $with The name of the relationship to process.
     * @return array The data with attached ORM relationships.
     * 
     * @throws RuntimeException If an invalid ORM type is specified.
     */
    private function __handleOrm(array $data, array $config, string $with): array
    {
        return match ($config['has']) {
            'many-x' => $this->__manyX($data, $config, $with),
            'many' => $this->__many($data, $config, $with),
            'one' => $this->__one($data, $config, $with),
            default => throw new RuntimeException("Invalid Orm Type({$config['has']})")
        };
    }

    /**
     * Handles many-to-many relationships where an intermediate table is used.
     * 
     * @param array $data The main data set.
     * @param array $config The configuration for the many-x relationship.
     * @param string $with The name of the relationship.
     * @return array The data with the many-x related data attached.
     */
    private function __manyX(array $data, array $config, string $with): array
    {
        $object = new $config['model'];

        $query = $this->applyCallback(
            $object->query()
                ->fetch(PDO::FETCH_ASSOC)
                ->select("p.*, t1.{$data[0]->table()}_id, t1.{$object->table()}_id")
                ->join($config['table'], "t1.{$object->table()}_id = p.id")
                ->where([
                    "t1.{$data[0]->table()}_id" => collect($data)
                        ->pluck('id')
                        ->unique()
                        ->all()
                ]),
            $config
        );

        return $this->__parseOrmData($data, $query->result(), $object, $with);
    }

    /**
     * Handles one-to-many relationships.
     * 
     * @param array $data The main data set.
     * @param array $config The configuration for the many relationship.
     * @param string $with The name of the relationship.
     * @return array The data with the many related data attached.
     */
    private function __many(array $data, array $config, string $with): array
    {
        $object = new $config['model'];

        $query = $this->applyCallback(
            $object->query()
                ->select()
                ->fetch(PDO::FETCH_ASSOC)
                ->where([
                    "{$data[0]->table()}_id" => collect($data)
                        ->pluck('id')
                        ->unique()
                        ->all()
                ]),
            $config
        );

        return $this->__parseOrmData(
            $data,
            $query->result(),
            $object,
            $with
        );
    }

    /**
     * Parses and attaches related ORM data to the main data set.
     * 
     * @param array $data The main data set.
     * @param array $objects The related objects fetched based on the ORM configuration.
     * @param object $object The related model object.
     * @param string $with The name of the relationship.
     * @return array The data with attached ORM data.
     */
    private function __parseOrmData(array $data, $objects, $object, $with): array
    {
        foreach ($data as $d) {
            if (!isset($d->orm[$with])) {
                $d->orm[$with] = [];
            }
            foreach ($objects as $o) {
                if ($o["{$data[0]->table()}_id"] == $d->id) {
                    $d->orm[$with][] = $object->load($o);
                }
            }
        }
        return $data;
    }

    /**
     * Handles one-to-one relationships.
     * 
     * @param array $data The main data set.
     * @param array $config The configuration for the one relationship.
     * @param string $with The name of the relationship.
     * @return array The data with the one related data attached.
     */
    private function __one(array $data, array $config, string $with): array
    {
        $object = new $config['model'];

        $objects = $this->applyCallback(
            $object->query()
                ->select()
                ->fetch(PDO::FETCH_ASSOC)
                ->where([
                    'id' => collect($data)
                        ->pluck("{$object->table()}_id")
                        ->unique()
                        ->all()
                ]),
            $config
        )
            ->result();

        foreach ($data as $d) {
            if (!isset($d->orm[$with])) {
                $d->orm[$with] = false;
            }
            foreach ($objects as $o) {
                if ($o['id'] == $d->{"{$object->table()}_id"}) {
                    $d->orm[$with] = $object->load($o);
                    break;
                }
            }
        }
        return $data;
    }

    /**
     * Applies a callback to a query object, if provided in the configuration.
     * The callback should accept a query object as its first argument.
     * 
     * @param query $query The query object.
     * @param array $config The ORM relationship configuration.
     * @return query The modified query object.
     */
    private function applyCallback(query $query, array $config): query
    {
        if (isset($config['callback']) && is_callable($config['callback'])) {
            $query = call_user_func($config['callback'], $query);
        }

        return $query;
    }

    /**
     * Extracts form fields for ORM-related data based on the configuration.
     * 
     * @return array An array of form fields for managing ORM relationships.
     */
    protected function extractOrmFields(): array
    {
        $fields = [];
        foreach ($this->orm() as $with => $config) {
            if (!in_array($config['has'], ['many-x', 'one']) || ($config['formIgnore'] ?? false)) {
                continue;
            }

            $model = new $config['model'];
            $field = [
                'type' => 'select',
                'required' => true,
                'label' => ucfirst(str_replace(['_', '-', '.'], ' ', $with)),
                'options' => collect($model->get()->result())->mapK(fn($d) => [$d->id => (string) $d])->all(),
            ];

            if ($config['has'] == 'one') {
                $fields[] = array_merge($field, [
                    'name' => "{$model->table()}_id",
                    'value' => $this->{"{$model->table()}_id"} ?? null,
                ]);
            } elseif ($config['has'] == 'many-x') {
                $values = isset($this->id) ? collect($this->{$with})->pluck('id')->all() : [];
                $fields[] = array_merge($field, [
                    'name' => $config['table'],
                    'multiple' => true,
                    'value' => $values,
                ]);
                if (!empty($values)) {
                    $fields[] = [
                        'type' => 'hidden',
                        'name' => "_{$config['table']}",
                        'value' => implode(',', $values)
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * Validates and handles form submissions for many-x relationships, 
     * synchronizing the intermediate table records.
     * 
     * @return void
     */
    protected function checkOrmFormFields(): void
    {
        foreach ($this->orm() as $config) {
            if ($config['has'] != 'many-x') {
                continue;
            }

            $old_ids = explode(',', request()->post("_{$config['table']}", ''));
            $new_ids = request()->post($config['table'], []);
            $remove_ids = array_diff($old_ids, $new_ids);
            $create_ids = array_diff($new_ids, $old_ids);

            $model = new $config['model'];
            $query = new query(application::$app->database, $config['table']);

            if (!empty($create_ids)) {
                $ids = collect($create_ids)->map(fn($id) => [
                    "{$model->table()}_id" => $id,
                    "{$this->table()}_id" => $this->id,
                ])->all();
                $query->insert(array_values($ids));
            }

            if (!empty($remove_ids)) {
                $query->delete(["{$this->table()}_id" => $this->id, "{$model->table()}_id" => $remove_ids]);
            }
        }
    }
}
