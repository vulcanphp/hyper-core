<?php

namespace hyper\utils;

use RuntimeException;

/**
 * Class settings
 * 
 * A utility class to manage multi-dimensional associative array data, often used for storing settings.
 * Data can be set, retrieved, and removed, and the class serializes data to a file upon destruction if changes were made.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class settings
{
    /**
     * Stores the multi-dimensional associative array data.
     * 
     * @var array $data
     */
    private array $data = [];

    /**
     * Tracks if data has been modified to ensure persistence upon destruction.
     * 
     * @var bool $isChanged
     */
    private bool $isChanged = false;

    /**
     * settings constructor.
     *
     * Initializes the data from a serialized file if it exists.
     *
     * @param string $filepath The path to the file where data will be serialized and stored.
     */
    public function __construct(private string $filepath)
    {
        if (file_exists($this->filepath)) {
            $this->data = (array) unserialize(
                file_get_contents($this->filepath)
            );
        }
    }

    /**
     * Checks if a specific key exists and is non-empty in a given layer.
     *
     * @param string $layer The name of the layer in the array.
     * @param string $key The specific key within the layer to check.
     * @return bool True if the key exists and is non-empty; otherwise, false.
     */
    public function has(string $layer, string $key): bool
    {
        return isset($this->data[$layer][$key]) && !empty($this->data[$layer][$key]);
    }

    /**
     * Checks if a specific key in a layer has a boolean true value.
     *
     * @param string $layer The layer name.
     * @param string $key The key within the layer.
     * @return bool True if the key's value is true; otherwise, false.
     */
    public function is(string $layer, string $key): bool
    {
        return isset($this->data[$layer][$key]) && boolval($this->data[$layer][$key]) === true;
    }

    /**
     * Retrieves the value of a key within a layer or returns a default if the key does not exist.
     *
     * @param string $layer The layer name.
     * @param string $key The key name; if '*', returns the entire layer.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The value of the key or the default.
     */
    public function get(string $layer, string $key, $default = null): mixed
    {
        return $key === '*' ? ($this->data[$layer] ?? $default) : ($this->has($layer, $key) ? $this->data[$layer][$key] : $default);
    }

    /**
     * Sets a value for a key within a layer, marking data as changed if modified.
     *
     * @param string $layer The layer name.
     * @param string $key The key within the layer.
     * @param mixed $value The value to assign to the key.
     * @return self Returns the instance to allow method chaining.
     */
    public function set(string $layer, string $key, $value): self
    {
        if (!$this->has($layer, $key) || $this->get($layer, $key) != $value) {
            $this->isChanged = true;
            $this->data[$layer][$key] = $value;
        }

        return $this;
    }

    /**
     * Removes a specific key within a layer, marking data as changed.
     *
     * @param string $layer The layer name.
     * @param string $key The key to remove.
     * @return self Returns the instance to allow method chaining.
     */
    public function remove(string $layer, string $key): self
    {
        $this->isChanged = true;
        unset($this->data[$layer][$key]);
        return $this;
    }

    /**
     * Sets multiple key-value pairs within a layer from a given configuration array.
     *
     * @param string $layer The layer name.
     * @param array $config Associative array of key-value pairs to set in the layer.
     * @return self Returns the instance to allow method chaining.
     */
    public function setup(string $layer, array $config): self
    {
        foreach ($config as $key => $value) {
            $this->set($layer, $key, $value);
        }

        return $this;
    }

    /**
     * Serializes and saves data to a file if changes were made during the instance's lifetime.
     * 
     * Automatically called upon object destruction.
     */
    public function __destruct()
    {
        if ($this->isChanged) {
            if (!is_writable($this->filepath) && !chmod($this->filepath, 0777)) {
                throw new RuntimeException("Settings file:{$this->filepath} is not writable.");
            }

            file_put_contents($this->filepath, serialize($this->data), LOCK_EX);
        }
    }
}
