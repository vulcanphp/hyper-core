<?php

namespace hyper;

use PDO;

/**
 * Class model
 *
 * This class provides a base for models, handling database operations and entity management.
 * It includes CRUD operations, data decoding, and dynamic method invocation.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class model
{
    /**
     * @var string The table name associated with this model.
     */
    protected string $table;

    /**
     * @var int The ID of the model instance, representing the primary key.
     */
    public int $id;

    /**
     * model constructor.
     * Initializes the model instance and decodes any previously saved data if an ID is set.
     */
    public function __construct()
    {
        if (isset($this->id)) {
            $this->decodeSavedData();
        }
    }

    /**
     * Creates a new query instance for the model's table.
     *
     * @return query Returns a query object for database operations.
     */
    public static function query(): query
    {
        $model = new static();

        // Return a new database query builder object.
        return new query(
            database: application::$app->database,
            table: $model->table()
        );
    }

    /**
     * Gets a new query object with SELECT applied and ready for fetching.
     *
     * @return query A query object set to fetch data as instances of the current model.
     */
    public static function get(): query
    {
        return self::query()
            ->select()
            ->fetch(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Finds a model by its primary key ID.
     *
     * @param int $id The ID of the model to retrieve.
     * @return false|static The found model instance or false if not found.
     */
    public static function find(int $id): false|static
    {
        return self::get()
            ->where(['id' => $id])
            ->first();
    }

    /**
     * Loads an array of data into a new model instance.
     *
     * @param array $data Key-value pairs of model properties.
     * @return static A model instance populated with the given data.
     */
    public static function load(array $data): static
    {
        // Create & Holds a new model.
        $model = new static();

        // Push every property of this model.
        foreach ($data as $key => $value) {
            if (property_exists($model, $key)) {
                $model->{$key} = $value;
            }
        }

        // Decode model properties from json to array.
        $model->decodeSavedData();

        // Return the new model object.
        return $model;
    }

    /**
     * Saves the model to the database, either updating or creating a new entry.
     *
     * @return int|bool The ID of the saved model or false on failure.
     */
    public function save(): int|bool
    {
        // Apply events for before save and encode array into json string. 
        $data = $this->beforeSaveData(
            $this->toArray()
        );

        // Update this records if it has an id, else insert this records into database.
        $status = isset($this->id) ? $this->query()->update($data, ['id' => $this->id]) : $this->query()->insert($data);

        // Save model id if it is newly created.
        if (is_int($status)) {
            $this->id = $status;
        }

        // Apply model events after saved the record.
        $this->decodeSavedData();
        $event_status = $this->afterSavedData();

        if (!$status && $event_status) {
            $status = true;
        }

        // Return database operation status.
        return $status;
    }

    /**
     * Removes the model from the database.
     *
     * @return bool True if removal was successful, false otherwise.
     */
    public function remove(): bool
    {
        // Call events when this model is about to be deleted.
        $this->beforeRemoveData();

        // Remove this record from database.
        $removed = $this->query()->delete(['id' => $this->id]);

        // Call events when this model is deleted.
        if ($removed) {
            $this->afterRemovedData();
        }

        // Returns database operation status.
        return $removed;
    }

    /**
     * Prepares data before saving, such as encoding arrays to JSON.
     *
     * @param array $data The data to prepare.
     * @return array The prepared data for database insertion or update.
     */
    private function beforeSaveData(array $data): array
    {
        // Call beforeSave event
        if (method_exists($this, 'beforeSave')) {
            $data = $this->beforeSave($data);
        }

        // Check uploaded files, if model has files enabled.
        if (method_exists($this, 'uploadChanges')) {
            $data = $this->uploadChanges($data);
        }

        // Parse model property into string if the are in array.
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value) ? json_encode($value) : $value;
        }

        // returns associative array of model properties. 
        return $data;
    }

    /**
     * Callback after saving data to handle post-save tasks.
     * 
     * @return bool
     */
    private function afterSavedData(): bool
    {
        $status = false;

        // Call afterSave event
        if (method_exists($this, 'afterSave')) {
            $this->afterSave();
        }

        // Check ORM form fields changes 
        if (method_exists($this, 'checkOrmFormFields')) {
            $status = $this->checkOrmFormFields();
        }

        return $status;
    }

    /**
     * Called before removing the model from the database.
     * 
     * @return void
     */
    private function beforeRemoveData(): void
    {
        // Call beforeRemove event
        if (method_exists($this, 'beforeRemove')) {
            $this->beforeRemove();
        }
    }

    /**
     * Callback after removing data to handle post-remove tasks.
     * 
     * @return void
     */
    private function afterRemovedData(): void
    {
        // Call afterRemove event
        if (method_exists($this, 'afterRemove')) {
            $this->afterRemove();
        }

        // Removed uploaded files wich are associated with this model
        if (method_exists($this, 'removeUploaded')) {
            $this->removeUploaded($this->toArray());
        }
    }

    /**
     * Decodes JSON strings in properties to their original formats.
     * 
     * @return void
     */
    private function decodeSavedData(): void
    {
        // Go Through all the properties of this model.
        foreach ($this->toArray() as $key => $value) {
            /** 
             * if the property is json format then decode json
             * string to associative array.
             * 
             * if property is a array then replace it to model.
             */
            if (is_string($value) && (strpos($value, '[') === 0 || strpos($value, '{') === 0)) {
                $value = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    /**
     * Gets the table name for the model.
     *
     * @return string The associated table name.
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Converts the model to an associative array.
     *
     * @return array An array of model properties and their values.
     */
    public function toArray(): array
    {
        return get_object_vars(...)->__invoke($this);
    }

    /**
     * Handles dynamic method calls to the query instance.
     *
     * @param string $name The method name.
     * @param array $arguments The method arguments.
     * @return mixed The result of the query method call.
     */
    public function __call($name, $arguments)
    {
        return call_user_func([$this->get(), $name], ...$arguments);
    }

    /**
     * Handles static method calls to the query instance.
     *
     * @param string $name The method name.
     * @param array $arguments The method arguments.
     * @return mixed The result of the query method call.
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func([self::get(), $name], ...$arguments);
    }

    /**
     * Converts the model to a string representation.
     *
     * @return string A string representation of the model instance.
     */
    public function __toString()
    {
        return sprintf('model: (%s), %s(%d)', static::class, $this->table, $this->id ?? '#');
    }
}
