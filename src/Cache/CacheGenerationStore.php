<?php

declare(strict_types=1);

namespace OperationalStatus\Cache;

interface CacheGenerationStore
{
    public function current(): int;

    public function increment(): int;
}
