<?php

namespace hyper;

use PDO;

class model
{
    protected string $table;
    public int $id;

    public function __construct()
    {
        if (isset($this->id)) {
            $this->decodeSavedData();
        }
    }

    public static function query(): query
    {
        $model = new static();
        return new query(database: application::$app->database, table: $model->table());
    }

    public static function get(): query
    {
        return self::query()->select()->fetch(PDO::FETCH_CLASS, static::class);
    }

    public static function find(int $id): false|static
    {
        return self::get()->where(['id' => $id])->first();
    }

    public static function load(array $data): self
    {
        $model = new static();
        foreach ($data as $key => $value) {
            if (property_exists($model, $key)) {
                $model->{$key} = $value;
            }
        }
        $model->decodeSavedData();
        return $model;
    }

    public function save(): int|bool
    {
        $data = $this->beforeSaveData($this->toArray());
        if (isset($this->id)) {
            $status = $this->query()->update($data, ['id' => $this->id]);
        } else {
            $status = $this->query()->insert($data);
        }
        if (is_int($status)) {
            $this->id = $status;
        }
        if ($status) {
            $this->decodeSavedData();
            $this->afterSavedData();
        }
        return $status;
    }

    public function remove(): bool
    {
        $removed = $this->query()->delete(['id' => $this->id]);
        if ($removed) {
            $this->afterRemovedData();
        }
        return $removed;
    }

    private function beforeSaveData(array $data): array
    {
        if (method_exists($this, 'uploadChanges')) {
            $data = $this->uploadChanges($data);
        }
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value) ? json_encode($value) : $value;
        }
        return $data;
    }

    private function afterSavedData(): void
    {
        if (method_exists($this, 'checkOrmFormFields')) {
            $this->checkOrmFormFields();
        }
    }

    private function afterRemovedData(): void
    {
        if (method_exists($this, 'removeUploaded')) {
            $this->removeUploaded($this->toArray());
        }
    }

    private function decodeSavedData(): void
    {
        foreach ($this->toArray() as $key => $value) {
            if (is_string($value) && (strpos($value, '[') === 0 || strpos($value, '{') === 0)) {
                $value = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    public function table(): string
    {
        return $this->table;
    }

    public function toArray(): array
    {
        return get_object_vars(...)->__invoke($this);
    }

    public function __call($name, $arguments)
    {
        return call_user_func([$this->get(), $name], ...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func([self::get(), $name], ...$arguments);
    }

    public function __toString()
    {
        return sprintf('model: (%s), %s(%d)', static::class, $this->table, $this->id);
    }
}
