<?php

namespace hyper\utils;

class collect
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): self
    {
        return new self($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function get(int|string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function in(mixed $needle): bool
    {
        return in_array($needle, $this->items);
    }

    public function add(int|string $key, $value): self
    {
        $this->items[$key] = $value;
        return $this;
    }

    public function remove(int|string $key): self
    {
        unset($this->items[$key]);
        return $this;
    }

    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    public function push(...$values): self
    {
        array_push($this->items, ...$values);
        return $this;
    }

    public function shift(): mixed
    {
        return array_shift($this->items);
    }

    public function unshift(...$values): self
    {
        array_unshift($this->items, ...$values);
        return $this;
    }

    public function merge(array ...$arrays): self
    {
        $this->items = array_merge($this->items, ...$arrays);
        return $this;
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function sort(?callable $callback = null): self
    {
        if ($callback) {
            usort($this->items, $callback);
        } else {
            sort($this->items);
        }
        return $this;
    }

    public function sortKeys(?callable $callback = null): self
    {
        if ($callback) {
            uksort($this->items, $callback);
        } else {
            ksort($this->items);
        }
        return $this;
    }

    public function multiSort(string $column, bool $desc = false): self
    {
        $numbers = array_column($this->items, $column);
        if ($desc) {
            array_multisort($numbers, SORT_DESC, $this->items);
        } else {
            array_multisort($numbers, SORT_ASC, $this->items);
        }
        return $this;
    }

    public function sortBy(array $fields): self
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($fields) {
            foreach ($fields as $field => $direction) {
                if (!isset($a[$field]) || !isset($b[$field])) {
                    continue;
                }
                $comparison = $a[$field] <=> $b[$field];
                if ($comparison !== 0) {
                    return $direction === 'asc' ? $comparison : -$comparison;
                }
            }
            return 0;
        });
        return new self($items);
    }

    public function keys()
    {
        return new self(array_keys($this->items));
    }

    public function unique(): self
    {
        return new self(array_unique($this->items, SORT_REGULAR));
    }

    public function reverse(): self
    {
        return new self(array_reverse($this->items, true));
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items, array_keys($this->items)));
    }

    public function mapK(callable $callback): self
    {
        $result = [];
        foreach ($this->items as $key => $value) {
            $mapped = $callback($value, $key);
            foreach ($mapped as $newKey => $newValue) {
                $result[$newKey] = $newValue;
            }
        }
        return new self($result);
    }

    public function pluck(string $key): self
    {
        $results = array_map(fn ($item) => is_array($item) ? $item[$key] ?? null : (is_object($item) ? $item->$key ?? null : null), $this->items);
        return new self(array_filter($results));
    }

    public function slice(int $offset, ?int $limit = null, bool $preserve_keys = false)
    {
        return new self(array_slice($this->items, $offset, $limit, $preserve_keys));
    }

    public function where(string $key, $value): self
    {
        return new self(array_filter($this->items, fn ($item) => is_array($item) && ($item[$key] ?? null) === $value));
    }

    public function except(array $keys): self
    {
        $removeKeys = function ($array, $keys) use (&$removeKeys) {
            $filtered = [];
            foreach ($array as $key => $value) {
                if (!in_array($key, $keys)) {
                    $filtered[$key] = is_array($value) ? $removeKeys($value, $keys) : $value;
                }
            }
            return $filtered;
        };
        return new self($removeKeys($this->items, $keys));
    }

    public function only(array $keys): self
    {
        $filterKeys = function ($array, $keys) use (&$filterKeys) {
            $filtered = [];
            foreach ($array as $key => $value) {
                if (in_array($key, $keys)) {
                    $filtered[$key] = is_array($value) ? $filterKeys($value, $keys) : $value;
                } elseif (is_array($value)) {
                    $nested = $filterKeys($value, $keys);
                    if (!empty($nested)) {
                        $filtered[$key] = $nested;
                    }
                }
            }
            return $filtered;
        };
        return new self($filterKeys($this->items, $keys));
    }

    public function group(string $group): self
    {
        $array = [];
        foreach ($this->items as $k => $value) {
            $key = is_array($value) ? $value[$group] : (is_object($value) ? $value->$group : null);
            if ($key !== null) {
                $array[$key][$k] = $value;
            }
        }
        return new self($array);
    }

    public function find(callable $callback, $default = null)
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return $this->items[0] ?? $default;
        }
        return $this->find($callback, $default);
    }

    public function last(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return $this->items[count($this->items) - 1] ?? $default;
        }
        foreach (array_reverse($this->items, true) as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function toJson(): string
    {
        return json_encode($this->items);
    }

    public function toString(array|string $operator = ""): string
    {
        return implode($operator, $this->items);
    }
}
