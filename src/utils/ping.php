<?php

namespace hyper\utils;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class ping
 *
 * A helper class for making HTTP requests in PHP using cURL. Supports GET, POST, PUT, PATCH, and DELETE methods,
 * as well as custom headers, options, user agents, and file downloads.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ping
{
    /**
     * Configuration settings for the cURL request, including headers, options, download file location, and user agent.
     * 
     * @var array $config
     */
    protected array $config = [
        'headers' => [],
        'options' => [],
        'download' => null,
        'useragent' => null,
    ];

    /**
     * Sends an HTTP request to the specified URL with optional parameters.
     *
     * @param string $url The target URL.
     * @param array $params Optional query parameters to include in the request URL.
     * @return array The response data, including body, status code, final URL, and content length.
     * @throws RuntimeException If cURL initialization fails.
     */
    public function send(string $url, array $params = []): array
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        // Default cURL options for the request
        $defaultOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'utf-8',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $this->config['headers'],
            CURLOPT_URL => $url . (!empty($params) ? '?' . http_build_query($params) : '')
        ];

        // Add custom user agent if set
        if (isset($this->config['useragent'])) {
            $defaultOptions[CURLOPT_USERAGENT] = $this->config['useragent'];
        }

        // Set up file download if specified
        if ($this->config['download']) {
            $download = fopen($this->config['download'], 'w+');
            $defaultOptions[CURLOPT_FILE] = $download;
        }

        curl_setopt_array($curl, $defaultOptions);
        curl_setopt_array($curl, $this->config['options']);

        // Execute the cURL request and gather response data
        $response = [
            'body' => curl_exec($curl),
            'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'last_url' => curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
            'length' => curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
        ];

        // Close file if download was specified
        if ($this->config['download']) {
            fclose($download);
        }

        curl_close($curl);

        // Reset currenct object.
        $this->resetConfig();

        // The response data, including body, status code, final URL, and content length.
        return $response;
    }

    /**
     * Resets the configuration to its default state.
     */
    public function resetConfig(): void
    {
        $this->config = [
            'headers' => [],
            'options' => [],
            'download' => null,
            'useragent' => null,
        ];
    }

    /**
     * Sets a single cURL option.
     *
     * @param int $key The cURL option constant.
     * @param mixed $value The value for the option.
     * @return self
     */
    public function option(int $key, mixed $value): self
    {
        $this->config['options'][$key] = $value;
        return $this;
    }

    /**
     * Sets multiple cURL options at once.
     *
     * @param array $options Associative array of cURL options.
     * @return self
     */
    public function options(array $options): self
    {
        $this->config['options'] = array_replace($this->config['options'], $options);
        return $this;
    }

    /**
     * Sets the User-Agent header for the request.
     *
     * @param string $useragent The User-Agent string.
     * @return self
     */
    public function useragent(string $useragent): self
    {
        $this->config['useragent'] = $useragent;
        return $this;
    }

    /**
     * Adds a custom header to the request.
     *
     * @param string $key Header name.
     * @param string $value Header value.
     * @return self
     */
    public function header(string $key, string $value): self
    {
        $this->config['headers'][] = "$key: $value";
        return $this;
    }

    /**
     * Sets the file path to download the response to.
     *
     * @param string $location File path for download.
     * @return self
     */
    public function download(string $location): self
    {
        $this->config['download'] = $location;
        return $this;
    }

    /**
     * Sets fields for a POST request.
     *
     * @param mixed $fields The fields to include in the POST body. Can be an array or string.
     * @return self
     */
    public function postFields(mixed $fields): self
    {
        return $this->options([
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => is_array($fields) ? json_encode($fields) : $fields
        ]);
    }

    /**
     * Handles dynamic method calls for HTTP methods (GET, POST, PUT, PATCH, DELETE).
     *
     * @param string $name The HTTP method name.
     * @param array $arguments The arguments for the method.
     * @return array The response array from the send method.
     * @throws InvalidArgumentException If the HTTP method is not supported.
     */
    public function __call(string $name, array $arguments): array
    {
        $method = strtoupper($name);
        if (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->option(CURLOPT_CUSTOMREQUEST, $method);
            return $this->send(...$arguments);
        }

        throw new InvalidArgumentException("Undefined Method: {$name}");
    }

    /**
     * Handles static calls by creating a new instance and calling the dynamic method.
     *
     * @param string $name The HTTP method name.
     * @param array $arguments The arguments for the method.
     * @return mixed The response from the dynamic method call.
     */
    public static function __callStatic($name, $arguments)
    {
        $ping = new static();
        return call_user_func([$ping, $name], ...$arguments);
    }
}
