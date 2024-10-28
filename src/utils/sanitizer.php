<?php

namespace hyper\utils;

/**
 * Class sanitizer
 * 
 * Sanitizer class provides methods to sanitize and validate different data types.
 * It includes methods for emails, URLs, HTML, numbers, booleans, dates, and custom data arrays.
 * 
 * @package hyper\utils
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class sanitizer
{
    /**
     * Constructs a new sanitizer instance with optional initial data.
     *
     * @param array $data Key-value data array to be sanitized.
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * Sanitizes an email address.
     *
     * @param string $key Key in the data array to sanitize.
     * @return string|null Sanitized email or null if invalid.
     */
    public function email(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_SANITIZE_EMAIL) ?: null;
    }

    /**
     * Sanitizes plain text, with optional HTML tag stripping.
     *
     * @param string $key Key in the data array to sanitize.
     * @param bool $stripTags Whether to strip HTML tags from the text.
     * @return string|null Sanitized text or null if invalid.
     */
    public function text(string $key, bool $stripTags = true): ?string
    {
        $value = filter_var($this->get($key), FILTER_UNSAFE_RAW);
        return $stripTags && $value ? strip_tags($value) : $value;
    }

    /**
     * Escapes HTML special characters for safe output.
     *
     * @param string $key Key in the data array to sanitize.
     * @return string|null Sanitized HTML or null if invalid.
     */
    public function html(string $key): ?string
    {
        return htmlspecialchars($this->get($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitizes an integer value.
     *
     * @param string $key Key in the data array to sanitize.
     * @return int|null Sanitized integer or null if invalid.
     */
    public function number(string $key): ?int
    {
        return filter_var($this->get($key), FILTER_SANITIZE_NUMBER_INT) ?: null;
    }

    /**
     * Sanitizes a floating-point number.
     *
     * @param string $key Key in the data array to sanitize.
     * @return float|null Sanitized float or null if invalid.
     */
    public function float(string $key): ?float
    {
        return filter_var($this->get($key), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
    }

    /**
     * Sanitizes a boolean value.
     *
     * @param string $key Key in the data array to validate.
     * @return bool|null Sanitized boolean or null if invalid.
     */
    public function boolean(string $key): ?bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Sanitizes a URL.
     *
     * @param string $key Key in the data array to sanitize.
     * @return string|null Sanitized URL or null if invalid.
     */
    public function url(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_SANITIZE_URL) ?: null;
    }

    /**
     * Validates an IP address.
     *
     * @param string $key Key in the data array to validate.
     * @return string|null Valid IP address or null if invalid.
     */
    public function ip(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_VALIDATE_IP) ?: null;
    }

    /**
     * Sanitizes each element of an array using a provided callback function.
     *
     * @param string $key Key in the data array to sanitize.
     * @param callable $sanitizeFunction Callback function to sanitize each array element.
     * @return array Sanitized array.
     */
    public function array(string $key, callable $sanitizeFunction): array
    {
        $value = $this->get($key);
        return is_array($value) ? array_map($sanitizeFunction, $value) : [];
    }

    /**
     * Validates and formats a date string according to the specified format.
     *
     * @param string $key Key in the data array to validate.
     * @param string $format Date format to validate against (default 'Y-m-d').
     * @return string|null Validated date string or null if invalid.
     */
    public function date(string $key, string $format = 'Y-m-d'): ?string
    {
        $value = $this->get($key);
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value ? $date->format($format) : null;
    }

    /**
     * Sets a key-value pair in the sanitizer data array.
     *
     * @param string $key Key in the data array.
     * @param mixed $value Value to set.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Retrieves the value of a key from the data array or returns a default value.
     *
     * @param string $key Key to retrieve.
     * @param mixed $default Default value if key does not exist.
     * @return mixed Retrieved value or default.
     */
    public function get(string $key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Retrieves all sanitized data as an associative array.
     *
     * @return array All key-value pairs in the sanitizer data array.
     */
    public function all(): array
    {
        return $this->data;
    }
}
