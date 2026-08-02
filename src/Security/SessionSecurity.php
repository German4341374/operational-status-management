<?php

declare(strict_types=1);

namespace OperationalStatus\Security;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

final class SessionSecurity
{
    /** @return array<string, bool|int|string> */
    public function options(bool $secure): array
    {
        return [
            'cookie_httponly' => true,
            'cookie_secure' => $secure,
            'cookie_samesite' => 'Strict',
            'cookie_path' => '/',
            'gc_maxlifetime' => 3600,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_strict_mode' => true,
            'sid_length' => 48,
            'sid_bits_per_character' => 6,
        ];
    }

    public function create(string $directory, bool $secure): Session
    {
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Session directory could not be created.');
        }

        $storage = new NativeSessionStorage(
            $this->options($secure),
            new NativeFileSessionHandler($directory),
        );

        return new Session($storage);
    }
}
