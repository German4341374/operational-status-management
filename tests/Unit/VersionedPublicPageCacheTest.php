<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Cache\InMemoryCacheGenerationStore;
use OperationalStatus\Cache\VersionedPublicPageCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class VersionedPublicPageCacheTest extends TestCase
{
    public function testRepeatedReadUsesCachedPage(): void
    {
        $renders = 0;
        $cache = new VersionedPublicPageCache(new ArrayAdapter(), new InMemoryCacheGenerationStore(), 30);
        $renderer = static function () use (&$renders): string {
            return 'page-' . ++$renders;
        };

        self::assertSame('page-1', $cache->remember($renderer));
        self::assertSame('page-1', $cache->remember($renderer));
        self::assertSame(1, $renders);
    }

    public function testInvalidationMovesReadersToNewGeneration(): void
    {
        $renders = 0;
        $cache = new VersionedPublicPageCache(new ArrayAdapter(), new InMemoryCacheGenerationStore(), 30);
        $renderer = static function () use (&$renders): string {
            return 'page-' . ++$renders;
        };

        self::assertSame('page-1', $cache->remember($renderer));
        self::assertSame(2, $cache->invalidate());
        self::assertSame('page-2', $cache->remember($renderer));
    }

    public function testRenderRacingWithInvalidationIsNotCached(): void
    {
        $generations = new InMemoryCacheGenerationStore();
        $cache = new VersionedPublicPageCache(new ArrayAdapter(), $generations, 30);
        $renders = 0;

        self::assertSame('stale-render', $cache->remember(function () use (&$renders, $generations): string {
            ++$renders;
            $generations->increment();

            return 'stale-render';
        }));
        self::assertSame('fresh-render', $cache->remember(function () use (&$renders): string {
            ++$renders;

            return 'fresh-render';
        }));
        self::assertSame(2, $renders);
    }
}
