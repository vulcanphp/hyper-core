<?php

namespace hyper\utils;

use InvalidArgumentException;
use RuntimeException;

class ping
{
    protected array $config = [
        'headers'   => [],
        'options'   => [],
        'download'  => null,
        'useragent' => null,
    ];

    public function send(string $url, array $params = []): array
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $defaultOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING       => 'utf-8',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER     => $this->config['headers'],
            CURLOPT_URL            => $url . (!empty($params) ? '?' . http_build_query($params) : '')
        ];

        if (isset($this->config['useragent'])) {
            $defaultOptions[CURLOPT_USERAGENT] = $this->config['useragent'];
        }

        if ($this->config['download']) {
            $download = fopen($this->config['download'], 'w+');
            $defaultOptions[CURLOPT_FILE] = $download;
        }

        curl_setopt_array($curl, $defaultOptions);
        curl_setopt_array($curl, $this->config['options']);

        $response = [
            'body'     => curl_exec($curl),
            'status'   => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'last_url' => curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
            'length'   => curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
        ];

        if ($this->config['download']) {
            fclose($download);
        }

        curl_close($curl);
        $this->resetConfig();

        return $response;
    }

    public function resetConfig(): void
    {
        $this->config = [
            'headers'   => [],
            'options'   => [],
            'download'  => null,
            'useragent' => null,
        ];
    }

    public function option(int $key, mixed $value): self
    {
        $this->config['options'][$key] = $value;
        return $this;
    }

    public function options(array $options): self
    {
        $this->config['options'] = array_replace($this->config['options'], $options);
        return $this;
    }

    public function useragent(string $useragent): self
    {
        $this->config['useragent'] = $useragent;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->config['headers'][] = "$key: $value";
        return $this;
    }

    public function download(string $location): self
    {
        $this->config['download'] = $location;
        return $this;
    }

    public function postFields(mixed $fields): self
    {
        return $this->options([
            CURLOPT_POST        => 1,
            CURLOPT_POSTFIELDS  => is_array($fields) ? json_encode($fields) : $fields
        ]);
    }

    public function __call(string $name, array $arguments): array
    {
        $method = strtoupper($name);
        if (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->option(CURLOPT_CUSTOMREQUEST, $method);
            return $this->send(...$arguments);
        }

        throw new InvalidArgumentException("Undefined Method: {$name}");
    }

    public static function __callStatic($name, $arguments)
    {
        $ping = new static();
        return call_user_func([$ping, $name], ...$arguments);
    }
}
