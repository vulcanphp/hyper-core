<?php

namespace hyper;

/**
 * Class request
 * 
 * Handles and manages HTTP request data for the application, including
 * query parameters, POST data, file uploads, and server variables.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class request
{
    /**
     * HTTP request method (e.g., GET, POST).
     * @var string
     */
    public string $method;

    /**
     * Requested URI path.
     * @var string
     */
    public string $path;

    /**
     * Root URL of the application (protocol and host).
     * @var string
     */
    public string $rootUrl;

    /**
     * Full URL of the current request.
     * @var string
     */
    public string $url;

    /**
     * Query parameters from the URL.
     * @var array
     */
    public array $queryParams;

    /**
     * Parameters from POST data.
     * @var array
     */
    public array $postParams;

    /**
     * Uploaded files data.
     * @var array
     */
    public array $fileUploads;

    /**
     * Server parameters, including headers.
     * @var array
     */
    public array $serverParams;

    /**
     * Additional route parameters.
     * @var array
     */
    public array $routeParams;

    /**
     * Authenticated user of this request.
     * @var false|null|object|array
     */
    public false|null|object|array $user;

    /**
     * request constructor.
     * 
     * Initializes request properties based on global server data.
     */
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

    /**
     * Parses raw POST input data in JSON format.
     * 
     * @return array Parsed JSON data as an associative array.
     */
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

    /**
     * Parses the requested URI path, excluding query parameters.
     * 
     * @return string The URI path of the request.
     */
    private function parsePath(): string
    {
        $path = $this->serverParams['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        return $position !== false ? substr($path, 0, $position) : $path;
    }

    /**
     * Builds the root URL (protocol and host).
     * 
     * @return string Root URL of the application.
     */
    private function parseRootUrl(): string
    {
        $protocol = (!empty($this->serverParams['HTTPS']) && $this->serverParams['HTTPS'] === 'on') ? 'https://' : 'http://';
        return $protocol . ($this->serverParams['HTTP_HOST'] ?? '');
    }

    /**
     * Builds the full URL of the current request.
     * 
     * @return string Full URL including protocol, host, and path.
     */
    private function parseUrl(): string
    {
        return rtrim($this->rootUrl . '/' . ltrim($this->serverParams['REQUEST_URI'] ?? '', '/'), '/');
    }

    /**
     * Retrieves a query parameter value.
     * 
     * @param string $key The parameter key.
     * @param mixed $default Default value if key does not exist.
     * @return mixed The parameter value or default.
     */
    public function get(string $key, $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Retrieves a POST parameter value.
     * 
     * @param string $key The parameter key.
     * @param mixed $default Default value if key does not exist.
     * @return mixed The parameter value or default.
     */
    public function post(string $key, $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    /**
     * Retrieves an uploaded file by key.
     * 
     * @param string $key The file key.
     * @param mixed $default Default value if key does not exist.
     * @return mixed The file array or default.
     */
    public function file(string $key, $default = null): mixed
    {
        return $this->fileUploads[$key] ?? $default;
    }

    /**
     * Retrieves all request data (query, post, files) optionally filtered.
     * 
     * @param array $filter Optional list of keys to filter by.
     * @return array Merged array of request data.
     */
    public function all(array $filter = []): array
    {
        // Prepare all inputs from $_GET, $_POST, and $_FILES.
        $output = array_merge($this->queryParams, $this->postParams, $this->fileUploads);

        // Filter inputs if needed.
        if (!empty($filter)) {
            $output = array_intersect_key($output, array_flip($filter));
            foreach ($filter as $filterKey) {
                if (!array_key_exists($filterKey, $output)) {
                    $output[$filterKey] = null;
                }
            }
        }

        // Returns all input items.
        return $output;
    }

    /**
     * Retrieves a server parameter by key.
     * 
     * @param string $key The parameter key.
     * @param mixed $default Default value if key does not exist.
     * @return ?string The server parameter or default.
     */
    public function server(string $key, $default = null): ?string
    {
        $key = str_replace('-', '_', strtoupper($key));
        return $this->serverParams[$key] ?? $default;
    }

    /**
     * Retrieves a request header by name.
     * 
     * @param string $name Header name.
     * @param mixed $defaultValue Default if header is not set.
     * @return ?string The header value or default.
     */
    public function header(string $name, $defaultValue = null): ?string
    {
        $name = "HTTP_$name";
        return $this->server($name, $defaultValue);
    }

    /**
     * Checks if the 'Accept' header contains a specific content type.
     * 
     * @param string $contentType The content type to check.
     * @return bool True if content type is accepted, otherwise false.
     */
    public function accept(string $contentType): bool
    {
        return stripos($this->header('accept', ''), $contentType) !== false;
    }

    /**
     * Retrieves the client IP address from headers or returns false if invalid.
     * 
     * @return false|string Client IP address or false if invalid.
     */
    public function ip(): false|string
    {
        $ip = '';

        // Catch client ip address from headers or server parameters.
        if (!empty($this->header('client-ip'))) {
            $ip = $this->header('client-ip');
        } elseif (!empty($this->header('x-forwarded-for'))) {
            $ip = $this->header('x-forwarded-for');
        } elseif (!empty($this->header('x-forwarded'))) {
            $ip = $this->header('x-forwarded');
        } elseif (!empty($this->header('forwarded'))) {
            $ip = $this->header('forwarded');
        } elseif (!empty($this->header('forwarded-for'))) {
            $ip = $this->header('forwarded-for');
        } elseif (!empty($this->header('cf-connecting-ip'))) {
            $ip = $this->header('cf-connecting-ip');
        } elseif (!empty($this->server('remote-addr'))) {
            $ip = $this->server('remote-addr');
        }

        // Get the last ip address from comma separated list.
        if (!empty($ip)) {
            $ips = explode(',', $ip);
            $ip = trim(end($ips));
        }

        // Return a valid ip address of client, else return as false.
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
    }

    /**
     * Merge the server parameters with additional key-value pairs.
     * 
     * @param array $params Associative array of server parameters to extend.
     * @return void
     */
    public function mergeServerParams(array $params): void
    {
        $this->serverParams = array_merge($this->serverParams, $params);
    }

    /**
     * Merge the query parameters with additional key-value pairs.
     * 
     * @param array $params Associative array of query parameters to extend.
     * @return void
     */
    public function mergeQueryParams(array $params): void
    {
        $this->queryParams = array_merge($this->queryParams, $params);
    }

    /**
     * Merge the POST parameters with additional key-value pairs.
     * 
     * @param array $params Associative array of parameters to add.
     * @return void
     */
    public function mergePostParams(array $params): void
    {
        $this->postParams = array_merge($this->postParams, $params);
    }

    /**
     * Merge the file uploads with additional key-value pairs.
     * 
     * @param array $uploads Associative array of file uploads to add.
     * @return void
     */
    public function mergeFileUploads(array $uploads)
    {
        $this->fileUploads = array_merge($this->fileUploads, $uploads);
    }
}
