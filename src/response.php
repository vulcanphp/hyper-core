<?php

namespace hyper;

/**
 * Class response
 * 
 * Manages HTTP response handling, including setting headers, content, status codes,
 * JSON responses, redirects, and output filtering.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class response
{
    /**
     * Constructor
     * 
     * Initializes a new response instance with the provided content, status code, and headers.
     * 
     * @param string $content The response content.
     * @param int $statusCode The HTTP status code.
     * @param array $headers An associative array of headers to send with the response.
     */
    public function __construct(
        public string $content = '',
        public int $statusCode = 200,
        public array $headers = []
    ) {
    }

    /**
     * Sets the response content to a specified string, replacing any existing content.
     *
     * @param string $content The content to set in the response body.
     * @return $this Current response instance for method chaining.
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Appends content to the existing response body.
     *
     * @param string $content The content to append to the response body.
     * @return $this Current response instance for method chaining.
     */
    public function write(string $content): self
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Sets the response content to a JSON-encoded array and updates the status code and headers.
     *
     * @param array $data The data array to be JSON-encoded in the response body.
     * @param int $statusCode The HTTP status code for the JSON response (default is 200).
     * @return $this Current response instance for method chaining.
     */
    public function json(array $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->setContent(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $this;
    }

    /**
     * Redirects the user to a specified URL and optionally terminates script execution.
     *
     * @param string $url The URL to redirect to.
     * @param bool $replace Whether to replace the current headers (default is true).
     * @param int $httpCode Optional HTTP status code for the redirect (default is 0).
     */
    public function redirect(string $url, bool $replace = true, int $httpCode = 0): void
    {
        header("Location: $url", $replace, $httpCode);
        exit;
    }

    /**
     * Sets the HTTP status code for the response.
     *
     * @param int $statusCode The HTTP status code to set (e.g., 200, 404).
     * @return $this Current response instance for method chaining.
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Sets a header for the response.
     *
     * @param string $key The header name (e.g., 'Content-Type').
     * @param string $value The header value (e.g., 'application/json').
     * @return $this Current response instance for method chaining.
     */
    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Sends the HTTP response to the client, including headers, status code, and content.
     * Applies any output filters to the content before outputting it.
     */
    public function send(): void
    {
        // Set http response code and headers.
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }

        // send output to client.
        echo $this->content;
    }
}
