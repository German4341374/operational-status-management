<?php

declare(strict_types=1);

namespace OperationalStatus\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class LoginRateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $limit = 5,
    ) {}

    public function consume(string $clientKey): RateLimit
    {
        $safeKey = hash('sha256', $clientKey);
        $limiter = new FixedWindowLimiter(
            'admin-login-' . $safeKey,
            $this->limit,
            new \DateInterval('PT15M'),
            new CacheStorage($this->cache),
        );

        return $limiter->consume();
    }

    public function reset(string $clientKey): void
    {
        $safeKey = hash('sha256', $clientKey);
        (new FixedWindowLimiter(
            'admin-login-' . $safeKey,
            $this->limit,
            new \DateInterval('PT15M'),
            new CacheStorage($this->cache),
        ))->reset();
    }
}
