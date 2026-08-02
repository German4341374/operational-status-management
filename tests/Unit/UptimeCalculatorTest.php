<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Domain\UptimeCalculator;
use PHPUnit\Framework\TestCase;

final class UptimeCalculatorTest extends TestCase
{
    private readonly \DateTimeImmutable $from;
    private readonly \DateTimeImmutable $to;

    protected function setUp(): void
    {
        $this->from = new \DateTimeImmutable('2026-08-01T00:00:00Z');
        $this->to = new \DateTimeImmutable('2026-08-02T00:00:00Z');
    }

    public function testNoOutageMeansFullUptime(): void
    {
        self::assertSame(100.0, (new UptimeCalculator())->percentage([], $this->from, $this->to));
    }

    public function testOneHourOutageProducesExpectedPercentage(): void
    {
        $history = [
            ['status' => 'OPERATIONAL', 'changed_at' => '2026-08-01T13:00:00Z'],
            ['status' => 'MAJOR_OUTAGE', 'changed_at' => '2026-08-01T12:00:00Z'],
        ];

        self::assertSame(95.833, (new UptimeCalculator())->percentage($history, $this->from, $this->to));
    }

    public function testMaintenanceDoesNotReduceAvailability(): void
    {
        $history = [['status' => 'MAINTENANCE', 'changed_at' => '2026-08-01T10:00:00Z']];
        self::assertSame(100.0, (new UptimeCalculator())->percentage($history, $this->from, $this->to));
    }

    public function testInvalidIntervalIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new UptimeCalculator())->percentage([], $this->to, $this->from);
    }
}
