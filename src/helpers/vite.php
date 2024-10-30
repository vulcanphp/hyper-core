<?php

namespace hyper\helpers;

use hyper\utils\ping;

/**
 * Class vite
 * 
 * Helper class for integrating Vite with a PHP application. Handles configuration, 
 * development server checks, and generates script and link tags for JavaScript and CSS assets.
 * 
 * @package hyper\helpers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class vite
{
    /** @var array Configuration array for the Vite helper */
    private array $config;

    /**
     * Constructor to initialize the Vite helper with a configuration array or a string.
     * 
     * @param string|array $config The configuration settings or the entry file as a string.
     */
    public function __construct(string|array $config)
    {
        if (is_string($config)) {
            $config = ['entry' => $config];
        }

        // Set default parameneters into vite configuration.
        $this->config = array_merge([
            'scheme' => 'http://',
            'host' => 'localhost',
            'port' => 5133,
            'running' => null,
            'entry' => 'app.js',
            'root' => 'resources',
            'dist' => 'build',
            'dist_path' => 'public/resources/',
            'manifest' => null,
        ], $config);
    }

    /**
     * Retrieves a configuration value by key, with an optional default.
     * 
     * @param string $key The configuration key.
     * @param mixed $default The default value if the key is not found.
     * @return mixed The configuration value or the default.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Generates the full HTML output including JavaScript and CSS tags.
     * 
     * @return string The combined HTML string of JavaScript and CSS tags.
     */
    public function __toString(): string
    {
        return $this->jsTag($this->config('entry'))
            . $this->jsPreloadImports($this->config('entry'))
            . $this->cssTag($this->config('entry'));
    }

    /**
     * Checks if the Vite development server is running.
     * 
     * @param string $entry The entry file name to check.
     * @return bool True if the server is running, false otherwise.
     */
    public function isRunning(string $entry): bool
    {
        if ($this->config('running') !== null) {
            return $this->config('running');
        }

        if (!env('debug')) {
            return false;
        }

        // live check if vite development server is running or not
        $http = new Ping();
        $http->options([CURLOPT_TIMEOUT => 1, CURLOPT_NOBODY => true]);

        return $this->config['running'] = $http->get($this->serverUrl($entry))['status'] === 200;
    }

    /**
     * Generates a JavaScript tag for the given entry file.
     * 
     * @param string $entry The entry file name.
     * @return string The HTML script tag for the JavaScript file.
     */
    public function jsTag(string $entry): string
    {
        $url = $this->isRunning($entry) ? $this->serverUrl($entry) : $this->assetUrl($entry);

        return $url ? '<script type="module" crossorigin src="' . $url . '"></script>' : '';
    }

    /**
     * Generates HTML link tags to preload JavaScript imports for the given entry file.
     * 
     * @param string $entry The entry file name.
     * @return string The HTML link tags for preloading JavaScript imports.
     */
    public function jsPreloadImports(string $entry): string
    {
        if ($this->isRunning($entry)) {
            return '';
        }

        return array_reduce(
            $this->importsUrls($entry),
            fn($res, $url) => $res . '<link rel="modulepreload" href="' . $url . '">',
            ''
        );
    }

    /**
     * Generates a CSS tag for the given entry file.
     * 
     * @param string $entry The entry file name.
     * @return string The HTML link tag for the CSS file.
     */
    public function cssTag(string $entry): string
    {
        if ($this->isRunning($entry)) {
            return '';
        }

        return array_reduce(
            $this->cssUrls($entry),
            fn($tags, $url) => $tags . '<link rel="stylesheet" href="' . $url . '">',
            ''
        );
    }

    /**
     * Retrieves the Vite manifest file as an associative array.
     * 
     * @return array The manifest data from the Vite build.
     */
    public function getManifest(): array
    {
        $manifestPath = root_dir($this->config('dist_path') . $this->config('dist') . '/manifest.json');
        return $this->config['manifest'] ??=
            file_exists($manifestPath) ? (array) json_decode(file_get_contents($manifestPath), true) : [];
    }

    /**
     * Gets the asset URL for the given entry file based on the Vite manifest.
     * 
     * @param string $entry The entry file name.
     * @return string The URL for the asset.
     */
    public function assetUrl(string $entry): string
    {
        $manifest = $this->getManifest();
        return isset($manifest[$entry]) ? $this->distUrl($manifest[$entry]['file']) : '';
    }

    /**
     * Retrieves the URLs for JavaScript imports associated with the given entry file.
     * 
     * @param string $entry The entry file name.
     * @return array The array of URLs for JavaScript imports.
     */
    public function importsUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (!empty($manifest[$entry]['imports'])) {
            foreach ($manifest[$entry]['imports'] as $import) {
                $urls[] = $this->distUrl($manifest[$import]['file']);
            }
        }

        return $urls;
    }

    /**
     * Retrieves the URLs for CSS files associated with the given entry file.
     * 
     * @param string $entry The entry file name.
     * @return array The array of URLs for CSS files.
     */
    public function cssUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (!empty($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $file) {
                $urls[] = $this->distUrl($file);
            }
        }

        return $urls;
    }

    /**
     * Constructs the URL for the Vite development server.
     * 
     * @param string $path Optional path to append to the server URL.
     * @return string The full URL for the Vite development server.
     */
    private function serverUrl(string $path = ''): string
    {
        return sprintf(
            '%s%s:%d/%s/%s',
            $this->config('scheme'),
            $this->config('host'),
            $this->config('port'),
            trim($this->config('root'), '/'),
            trim($path, '/')
        );
    }

    /**
     * Constructs the URL for the asset in the distribution directory.
     * 
     * @param string $path Optional path to append to the distribution URL.
     * @return string The full URL for the asset.
     */
    private function distUrl(string $path = ''): string
    {
        return asset_url($this->config('dist') . '/' . ltrim($path));
    }
}
