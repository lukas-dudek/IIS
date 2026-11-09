<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        private array $query,
        private array $post,
        private array $server,
        private array $files,
        private array $cookies
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = rawurldecode($path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function postString(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function postInt(string $key, int $default = 0): int
    {
        $value = $this->post[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = strtoupper(str_replace('-', '_', $name));
        if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
            $value = $this->server[$key] ?? null;
        } else {
            $value = $this->server['HTTP_' . $key] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
}
