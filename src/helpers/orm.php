<?php

namespace hyper\helpers;

use hyper\application;
use hyper\query;
use PDO;
use RuntimeException;

trait orm
{
    protected array $orm;

    protected function orm(): array
    {
        return [];
    }

    public static function with(array|string $orm = '*'): query
    {
        $model = new static();
        $query = $model->get();
        $registeredOrm = $model->orm();
        if ($orm === '*') {
            $orm = array_keys($registeredOrm);
        }
        foreach ((array)$orm as $with) {
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

    public function getRegisteredOrm(): array
    {
        $registeredOrm = $this->orm();
        return $registeredOrm;
    }

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

    private function __handleOrm(array $data, array $config, string $with): array
    {
        return match ($config['has']) {
            'many-x' => $this->__manyX($data, $config, $with),
            'many' => $this->__many($data, $config, $with),
            'one' => $this->__one($data, $config, $with),
            default => throw new RuntimeException("Invalid Orm Type({$config['has']})")
        };
    }

    private function __manyX(array $data, array $config, string $with): array
    {
        $object = new $config['model'];
        return $this->__parseOrmData(
            $data,
            $object->query()
                ->fetch(PDO::FETCH_ASSOC)
                ->select("p.*, t1.{$data[0]->table()}_id, t1.{$object->table()}_id")
                ->join($config['table'], "t1.{$object->table()}_id = p.id")
                ->where(["t1.{$data[0]->table()}_id" => collect($data)->pluck('id')->unique()->all()])
                ->result(),
            $object,
            $with
        );
    }

    private function __many(array $data, array $config, string $with): array
    {
        $object = new $config['model'];
        return $this->__parseOrmData(
            $data,
            $object->query()
                ->select()
                ->fetch(PDO::FETCH_ASSOC)
                ->where(["{$data[0]->table()}_id" => collect($data)->pluck('id')->unique()->all()])
                ->result(),
            $object,
            $with
        );
    }

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

    private function __one(array $data, array $config, string $with): array
    {
        $object = new $config['model'];
        $objects = $object->query()
            ->select()
            ->fetch(PDO::FETCH_ASSOC)
            ->where(['id' => collect($data)->pluck("{$object->table()}_id")->unique()->all()])
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
                'label' => ucfirst(str_replace(['_', '-'], ' ', $with)),
                'options' => collect($model->get()->result())->mapK(fn ($d) => [$d->id => (string) $d])->all(),
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
                $ids = collect($create_ids)->map(fn ($id) => [
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
