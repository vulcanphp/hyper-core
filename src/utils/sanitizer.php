<?php

namespace hyper\utils;

class sanitizer
{
    public function __construct(private array $data = [])
    {
    }

    public function email(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_SANITIZE_EMAIL) ?: null;
    }

    public function text(string $key, bool $stripTags = true): ?string
    {
        $value = filter_var($this->get($key), FILTER_UNSAFE_RAW);
        return $stripTags && $value ? strip_tags($value) : $value;
    }

    public function html(string $key): ?string
    {
        return htmlspecialchars($this->get($key), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function number(string $key): ?int
    {
        return filter_var($this->get($key), FILTER_SANITIZE_NUMBER_INT) ?: null;
    }

    public function float(string $key): ?float
    {
        return filter_var($this->get($key), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: null;
    }

    public function boolean(string $key): ?bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function url(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_SANITIZE_URL) ?: null;
    }

    public function ip(string $key): ?string
    {
        return filter_var($this->get($key), FILTER_VALIDATE_IP) ?: null;
    }

    public function array(string $key, callable $sanitizeFunction): array
    {
        $value = $this->get($key);
        return is_array($value) ? array_map($sanitizeFunction, $value) : [];
    }

    public function date(string $key, string $format = 'Y-m-d'): ?string
    {
        $value = $this->get($key);
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value ? $date->format($format) : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
