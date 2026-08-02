<?php

declare(strict_types=1);

namespace OperationalStatus\Cache;

final class InMemoryCacheGenerationStore implements CacheGenerationStore
{
    public function __construct(private int $generation = 1) {}

    public function current(): int
    {
        return $this->generation;
    }

    public function increment(): int
    {
        return ++$this->generation;
    }
}
