<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Domain\ComponentStatus;
use OperationalStatus\Domain\OverallStatusCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OverallStatusCalculatorTest extends TestCase
{
    private OverallStatusCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new OverallStatusCalculator();
    }

    public function testEmptyCatalogIsOperational(): void
    {
        self::assertSame(ComponentStatus::OPERATIONAL, $this->calculator->calculate([]));
    }

    /** @param list<ComponentStatus|string> $statuses */
    #[DataProvider('statusScenarios')]
    public function testMostSevereComponentControlsOverall(array $statuses, ComponentStatus $expected): void
    {
        self::assertSame($expected, $this->calculator->calculate($statuses));
    }

    /** @return iterable<string, array{list<ComponentStatus|string>, ComponentStatus}> */
    public static function statusScenarios(): iterable
    {
        yield 'all operational' => [[ComponentStatus::OPERATIONAL, 'OPERATIONAL'], ComponentStatus::OPERATIONAL];
        yield 'maintenance is visible' => [['OPERATIONAL', 'MAINTENANCE'], ComponentStatus::MAINTENANCE];
        yield 'degradation outranks maintenance' => [['MAINTENANCE', 'DEGRADED'], ComponentStatus::DEGRADED];
        yield 'partial outage outranks degraded' => [['DEGRADED', 'PARTIAL_OUTAGE'], ComponentStatus::PARTIAL_OUTAGE];
        yield 'major outage dominates' => [['MAJOR_OUTAGE', 'OPERATIONAL', 'PARTIAL_OUTAGE'], ComponentStatus::MAJOR_OUTAGE];
    }
}
