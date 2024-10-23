<?php

namespace hyper;

class request
{
    public string $method, $path, $rootUrl, $url;
    public array $queryParams, $postParams, $fileUploads, $serverParams, $user, $params = [];

    public function __construct()
    {
        $this->serverParams = $_SERVER;
        $this->method = $this->serverParams['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->parsePath();
        $this->rootUrl = $this->parseRootUrl();
        $this->url = $this->parseUrl();
        $this->fileUploads = $_FILES;
        $this->queryParams = $_GET;
        $this->postParams = $_POST;
        $this->postParams = array_merge($this->postParams, $this->parsePhpInput());
    }

    private function parsePhpInput(): array
    {
        if ($this->method === 'POST' && empty($this->postParams)) {
            $params = file_get_contents('php://input');
            if (!empty($params)) {
                $params = json_decode($params, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $params;
                }
            }
        }
        return [];
    }

    private function parsePath(): string
    {
        $path = $this->serverParams['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        return $position !== false ? substr($path, 0, $position) : $path;
    }

    private function parseRootUrl(): string
    {
        $protocol = (!empty($this->serverParams['HTTPS']) && $this->serverParams['HTTPS'] === 'on') ? 'https://' : 'http://';
        return $protocol . ($this->serverParams['HTTP_HOST'] ?? '');
    }

    private function parseUrl(): string
    {
        return rtrim($this->rootUrl . '/' . ltrim($this->serverParams['REQUEST_URI'] ?? '', '/'), '/');
    }

    public function get(string $key, $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function post(string $key, $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    public function file(string $key, $default = null): mixed
    {
        return $this->fileUploads[$key] ?? $default;
    }

    public function all(array $filter = []): array
    {
        $output = array_merge($this->queryParams, $this->postParams, $this->fileUploads);
        if (!empty($filter)) {
            $output = array_intersect_key($output, array_flip($filter));
            foreach ($filter as $filterKey) {
                if (!array_key_exists($filterKey, $output)) {
                    $output[$filterKey] = null;
                }
            }
        }
        return $output;
    }

    public function server(string $key, $default = null): ?string
    {
        return $this->serverParams[$key] ?? $default;
    }

    public function header(string $name, $defaultValue = null): ?string
    {
        $name = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        return $this->server($name, $defaultValue);
    }

    public function accept(string $contentType): bool
    {
        return strpos($this->header('accept', ''), $contentType) !== false;
    }

    public function ip(): false|string
    {
        $ip = '';
        if (!empty($this->header('client-ip'))) {
            $ip = $this->header('client-ip');
        } elseif (!empty($this->header('x-forwarded-for'))) {
            $ips = explode(',', $this->header('x-forwarded-for'));
            $ip = trim(end($ips));
        } elseif (!empty($this->header('cf-connecting-ip'))) {
            $ip = $this->header('cf-connecting-ip');
        } elseif (!empty($this->header('remote-addr'))) {
            $ip = $this->header('remote-addr');
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
    }
}
