<?php

declare(strict_types=1);

namespace OperationalStatus\Cache;

use Psr\Cache\CacheItemPoolInterface;

final class VersionedPublicPageCache
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly CacheGenerationStore $generations,
        private readonly int $ttlSeconds,
    ) {}

    /** @param callable(): string $renderer */
    public function remember(callable $renderer): string
    {
        $generation = $this->generations->current();
        $item = $this->pool->getItem('public_status_' . $generation);
        if ($item->isHit() && is_string($item->get())) {
            return $item->get();
        }

        $html = $renderer();
        if ($generation === $this->generations->current()) {
            $item->set($html)->expiresAfter($this->ttlSeconds);
            $this->pool->save($item);
        }

        return $html;
    }

    public function invalidate(): int
    {
        return $this->generations->increment();
    }
}
