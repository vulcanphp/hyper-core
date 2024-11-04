<?php

namespace hyper\utils;

use RuntimeException;

/**
 * Class cache
 * 
 * Cache class for managing temporary file-based cache storage.
 * Stores serialized data as JSON in the filesystem for fast data retrieval.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class cache
{
    /** @var string Path to the cache file */
    protected string $cachePath;

    /** @var array Holds cached data in memory */
    protected array $cacheData = [];

    /** @var bool Indicates if expired data has been erased */
    protected bool $erased = false;

    /** @var bool Indicates if cache has been loaded from the filesystem */
    protected bool $cached = false;

    /** @var bool Tracks changes in cache data for saving on destruction */
    protected bool $changed = false;

    /**
     * Constructor to initialize the cache file path.
     *
     * @param string $name Unique identifier for the cache file.
     */
    public function __construct(protected string $name)
    {
        $this->cachePath = env('tmp_dir') . '/' . md5($name) . '.cache';
    }

    /**
     * Reloads the cache data from the file if it hasn't been loaded yet.
     *
     * @return self
     */
    public function reload(): self
    {
        if (!$this->cached) {
            // Cache is loaded, avoid moutiple loads.
            $this->cached = true;

            // Retrieve all cached entries for this object.
            $this->cacheData = file_exists($this->cachePath)
                ? json_decode(file_get_contents($this->cachePath), true)
                : [];

            debugger('cache', "Cache loaded ({$this->name}) from: {$this->cachePath}");
        }

        return $this;
    }

    /**
     * Checks if a cache key exists and optionally erases expired entries.
     *
     * @param string $key The key to check in cache.
     * @param bool $eraseExpired Whether to erase expired entries before checking.
     * @return bool
     */
    public function has(string $key, bool $eraseExpired = false): bool
    {
        // Relace cache data if not loaded.
        $this->reload();

        // Check if cache is already exists, else store it into cache. 
        if ($eraseExpired) {
            $this->eraseExpired();
        }

        // True if the key exists, otherwise false.
        return isset($this->cacheData[$key]);
    }

    /**
     * Stores data in the cache with an optional expiration time.
     *
     * @param string $key Unique identifier for the cached data.
     * @param mixed $data The data to cache.
     * @param string|null $expire Expiration time as a string (e.g., '+1 day').
     * @return self
     */
    public function store(string $key, mixed $data, ?string $expire = null): self
    {
        // Relace cache data if not loaded.
        $this->reload();

        // Push new entry into cacheData.
        $this->cacheData[$key] = [
            'time' => time(),
            'expire' => $expire !== null ? strtotime($expire) - time() : 0,
            'data' => serialize($data),
        ];

        // Changes applied, save this cache file.
        $this->changed = true;

        return $this;
    }

    /**
     * Loads data from cache or generates it using a callback if not present.
     *
     * @param string $key The cache key.
     * @param callable $callback Function to generate the data if not cached.
     * @param string|null $expire Optional expiration time.
     * @return mixed
     */
    public function load(string $key, callable $callback, ?string $expire = null): mixed
    {
        // Erase expired entries if enabled.
        if ($expire !== null) {
            $this->eraseExpired();
        }

        // Check if cache is already exists, else store it into cache. 
        if (!$this->has($key)) {
            $this->store($key, call_user_func($callback, $this), $expire);
        }

        // Retrieve entry from cache.
        return $this->retrieve($key);
    }

    /**
     * Retrieves data from the cache for given keys, optionally erasing expired entries.
     *
     * @param string|array $keys Cache key(s) to retrieve.
     * @param bool $eraseExpired Whether to erase expired entries before retrieval.
     * @return mixed
     */
    public function retrieve(string|array $keys, bool $eraseExpired = false): mixed
    {
        // Erase expired entries if enabled.
        if ($eraseExpired) {
            $this->eraseExpired();
        }

        // Holds the cached entries, which are only retriving.
        $results = [];

        // Retrieve the cached entries.
        foreach ((array) $keys as $key) {
            if ($this->has($key)) {
                $results[$key] = unserialize($this->cacheData[$key]['data']);
            }
        }

        // The retrieved data or null if not found.
        return is_array($keys) ? $results : ($results[$keys] ?? null);
    }

    /**
     * Retrieves all data from the cache, optionally erasing expired entries.
     *
     * @param bool $eraseExpired Whether to erase expired entries before retrieval.
     * @return array
     */
    public function retrieveAll(bool $eraseExpired = false): array
    {
        if ($eraseExpired) {
            $this->eraseExpired();
        }

        // An array of all cached data.
        return array_map(fn($entry) => unserialize($entry['data']), $this->cacheData);
    }

    /**
     * Erases specified cache entries.
     *
     * @param string|array $keys Cache key(s) to erase.
     * @return self
     */
    public function erase(string|array $keys): self
    {
        $this->reload();

        foreach ((array) $keys as $key) {
            unset($this->cacheData[$key]);
        }

        $this->changed = true;

        return $this;
    }

    /**
     * Erases expired cache entries based on their timestamps and expiration times.
     *
     * @return self
     */
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

    /**
     * Clears all cache data.
     *
     * @return self
     */
    public function flush(): self
    {
        $this->cacheData = [];
        $this->changed = true;

        debugger('cache', "Cache flushed ({$this->name}) from: {$this->cachePath}");
        return $this;
    }

    /**
     * Determines if a cache entry has expired.
     *
     * @param int $timestamp The creation timestamp of the entry.
     * @param int $expiration Expiration duration in seconds.
     * @return bool
     */
    private function isExpired(int $timestamp, int $expiration): bool
    {
        // True if expired, otherwise false.
        return $expiration !== 0 && ((time() - $timestamp) > $expiration);
    }

    /**
     * Destructor to save cache data to the filesystem if there are changes.
     */
    public function __destruct()
    {
        if ($this->changed) {
            // Set a temp direcotry to store caches. 
            $cacheDir = env('tmp_dir');

            // Check if cache direcoty exists, else create a new direcotry.
            if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true)) {
                throw new RuntimeException("Failed to create temp direcotry to sotre caches.");
            } elseif (!is_writable($cacheDir) && !chmod($cacheDir, 0777)) {
                throw new RuntimeException("Temp direcotry is not writable.");
            }

            // Save updated cache data into locoal filesystem.
            if (
                file_put_contents(
                    $this->cachePath,
                    json_encode($this->cacheData, JSON_UNESCAPED_UNICODE),
                    LOCK_EX
                )
            ) {
                debugger('cache', "Cache saved ({$this->name}) to: {$this->cachePath}");
            } else {
                debugger('cache', "Failed to save cache ({$this->name}) to: {$this->cachePath}");
            }
        }
    }
}
