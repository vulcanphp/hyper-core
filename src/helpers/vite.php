<?php

namespace hyper\helpers;

use hyper\utils\ping;

class vite
{
    private array $config;

    public function __construct(string|array $config)
    {
        if (is_string($config)) {
            $config = ['entry' => $config];
        }

        $this->config = array_merge([
            'scheme'    => 'http://',
            'host'      => 'localhost',
            'port'      => 5133,
            'running'   => null,
            'entry'     => 'app.js',
            'root'      => 'public/resources',
            'dist'      => 'public/resources/build',
            'manifest'  => null,
        ], $config);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function __toString(): string
    {
        return $this->jsTag($this->config('entry')) . $this->jsPreloadImports($this->config('entry')) . $this->cssTag($this->config('entry'));
    }

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

    public function jsTag(string $entry): string
    {
        $url = $this->isRunning($entry) ? $this->serverUrl($entry) : $this->assetUrl($entry);

        return $url ? '<script type="module" crossorigin src="' . $url . '"></script>' : '';
    }

    public function jsPreloadImports(string $entry): string
    {
        if ($this->isRunning($entry)) {
            return '';
        }

        return array_reduce(
            $this->importsUrls($entry),
            fn ($res, $url) => $res . '<link rel="modulepreload" href="' . $url . '">',
            ''
        );
    }

    public function cssTag(string $entry): string
    {
        if ($this->isRunning($entry)) {
            return '';
        }

        return array_reduce(
            $this->cssUrls($entry),
            fn ($tags, $url) => $tags . '<link rel="stylesheet" href="' . $url . '">',
            ''
        );
    }

    public function getManifest(): array
    {
        $manifestPath = root_dir($this->config('dist') . '/manifest.json');

        return $this->config['manifest'] ??= file_exists($manifestPath) ? (array) json_decode(file_get_contents($manifestPath), true) : [];
    }

    public function assetUrl(string $entry): string
    {
        $manifest = $this->getManifest();

        return isset($manifest[$entry]) ? $this->distUrl($manifest[$entry]['file']) : '';
    }

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

    private function distUrl(string $path = ''): string
    {
        return url($this->config('dist') . '/' . ltrim($path));
    }
}
