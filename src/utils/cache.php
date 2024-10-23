<?php

namespace hyper\utils;

class cache
{
    protected string $cachePath;
    protected array $cacheData = [];
    protected bool $erased = false, $cached = false, $changed = false;

    public function __construct(protected string $name)
    {
        $this->cachePath = env('tmp_dir') . '/' . md5($name) . '.cache';
    }

    public function reload(): self
    {
        if (!$this->cached) {
            $this->cached = true;
            $this->cacheData = file_exists($this->cachePath)
                ? json_decode(file_get_contents($this->cachePath), true)
                : [];
            debugger('cache', "cache loaded ({$this->name}) from: {$this->cachePath}");
        }
        return $this;
    }

    public function has(string $key, bool $eraseExpired = false): bool
    {
        $this->reload();
        if ($eraseExpired) {
            $this->eraseExpired();
        }
        return isset($this->cacheData[$key]);
    }

    public function store(string $key, mixed $data, ?string $expire = null): self
    {
        $this->reload();
        $this->cacheData[$key] = [
            'time' => time(),
            'expire' => $expire !== null ? strtotime($expire) - time() : 0,
            'data' => serialize($data),
        ];
        $this->changed = true;
        return $this;
    }

    public function load(string $key, callable $callback, ?string $expire = null): mixed
    {
        if ($expire !== null) {
            $this->eraseExpired();
        }
        if (!$this->has($key)) {
            $this->store($key, call_user_func($callback, $this), $expire);
        }
        return $this->retrieve($key);
    }

    public function retrieve(string|array $keys, bool $eraseExpired = false): mixed
    {
        if ($eraseExpired) {
            $this->eraseExpired();
        }
        $results = [];
        foreach ((array)$keys as $key) {
            if ($this->has($key)) {
                $results[$key] = unserialize($this->cacheData[$key]['data']);
            }
        }
        return is_array($keys) ? $results : ($results[$keys] ?? null);
    }

    public function retrieveAll(bool $eraseExpired = false): array
    {
        if ($eraseExpired) {
            $this->eraseExpired();
        }
        return array_map(fn ($entry) => unserialize($entry['data']), $this->cacheData);
    }

    public function erase(string|array $keys): self
    {
        $this->reload();
        foreach ((array)$keys as $key) {
            unset($this->cacheData[$key]);
        }
        $this->changed = true;
        return $this;
    }

    public function eraseExpired(): self
    {
        $this->reload();
        if (!$this->erased) {
            $this->erased = true;
            foreach ($this->cacheData as $key => $entry) {
                if ($this->isExpired($entry['time'], $entry['expire'])) {
                    unset($this->cacheData[$key]);
                    $this->changed = true;
                }
            }
        }
        return $this;
    }

    public function flush(): self
    {
        $this->cacheData = [];
        $this->changed = true;
        debugger('cache', "cache flushed ({$this->name}) from: {$this->cachePath}");
        return $this;
    }

    private function isExpired(int $timestamp, int $expiration): bool
    {
        return $expiration !== 0 && ((time() - $timestamp) > $expiration);
    }

    public function __destruct()
    {
        if ($this->changed) {
            if (!is_dir($cacheDir = env('tmp_dir'))) {
                mkdir($cacheDir, 0777, true);
            }
            if (file_put_contents($this->cachePath, json_encode($this->cacheData), LOCK_EX)) {
                debugger('cache', "cache saved ({$this->name}) to: {$this->cachePath}");
            } else {
                debugger('cache', "failed to save cache ({$this->name}) to: {$this->cachePath}");
            }
        }
    }
}
