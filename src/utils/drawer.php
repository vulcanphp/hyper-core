<?php

namespace hyper\utils;

class drawer
{
    private array $data = [];
    private bool $isChanged = false;

    public function __construct(private string $filepath)
    {
        if (file_exists($this->filepath)) {
            $this->data = (array) unserialize(
                file_get_contents($this->filepath)
            );
        }
    }

    public function has(string $layer, string $key): bool
    {
        return isset($this->data[$layer][$key]) && !empty($this->data[$layer][$key]);
    }

    public function is(string $layer, string $key): bool
    {
        return isset($this->data[$layer][$key]) && boolval($this->data[$layer][$key]) === true;
    }

    public function get(string $layer, string $key, $default = null): mixed
    {
        return $key === '*' ? ($this->data[$layer] ?? $default) : ($this->has($layer, $key) ? $this->data[$layer][$key] : $default);
    }

    public function layer(string $layer, $default = []): array
    {
        return $this->data[$layer] ?? $default;
    }

    public function set(string $layer, string $key, $value): self
    {
        if (!$this->has($layer, $key) || $this->get($layer, $key) != $value) {
            $this->isChanged = true;
            $this->data[$layer][$key] = $value;
        }

        return $this;
    }

    public function remove(string $layer, string $key): self
    {
        $this->isChanged = true;
        unset($this->data[$layer][$key]);
        return $this;
    }

    public function setup(string $layer, array $config): self
    {
        foreach ($config as $key => $value) {
            $this->set($layer, $key, $value);
        }

        return $this;
    }

    public function __destruct()
    {
        if ($this->isChanged) {
            file_put_contents($this->filepath, serialize($this->data), LOCK_EX);
        }
    }
}
