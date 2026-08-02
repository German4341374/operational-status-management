<?php

declare(strict_types=1);

namespace OperationalStatus\Support;

final class Config
{
    /** @param array<string, string> $values */
    public function __construct(private readonly array $values) {}

    public static function fromEnvironment(): self
    {
        $keys = [
            'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_SECRET', 'SESSION_SECURE', 'DATABASE_URL',
            'DATABASE_USER', 'DATABASE_PASSWORD', 'ADMIN_USERNAME', 'ADMIN_PASSWORD_HASH', 'PUBLIC_CACHE_TTL',
        ];
        $values = [];
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if (false !== $value) {
                $values[$key] = (string) $value;
            }
        }

        return new self($values);
    }

    public function require(string $key): string
    {
        $value = trim($this->values[$key] ?? '');
        if ('' === $value) {
            throw new \RuntimeException(sprintf('Required environment variable %s is missing.', $key));
        }

        return $value;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->values[$key] ?? $default, FILTER_VALIDATE_BOOL);
    }

    public function int(string $key, int $default): int
    {
        $value = filter_var($this->values[$key] ?? $default, FILTER_VALIDATE_INT);

        return false === $value ? $default : (int) $value;
    }
}
