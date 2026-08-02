<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Domain\IncidentLifecycle;
use OperationalStatus\Domain\IncidentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IncidentLifecycleTest extends TestCase
{
    /** @return iterable<string, array{IncidentStatus, IncidentStatus}> */
    public static function validTransitions(): iterable
    {
        yield 'investigation identifies cause' => [IncidentStatus::INVESTIGATING, IncidentStatus::IDENTIFIED];
        yield 'identified fix enters monitoring' => [IncidentStatus::IDENTIFIED, IncidentStatus::MONITORING];
        yield 'monitoring can reopen identification' => [IncidentStatus::MONITORING, IncidentStatus::IDENTIFIED];
        yield 'monitoring resolves' => [IncidentStatus::MONITORING, IncidentStatus::RESOLVED];
        yield 'incident can resolve while investigating' => [IncidentStatus::INVESTIGATING, IncidentStatus::RESOLVED];
    }

    #[DataProvider('validTransitions')]
    public function testAllowsDocumentedTransitions(IncidentStatus $from, IncidentStatus $to): void
    {
        (new IncidentLifecycle())->assertTransition($from, $to);
        self::addToAssertionCount(1);
    }

    public function testResolvedIncidentIsTerminal(): void
    {
        $this->expectException(\DomainException::class);
        (new IncidentLifecycle())->assertTransition(IncidentStatus::RESOLVED, IncidentStatus::INVESTIGATING);
    }

    public function testRepeatedStatusIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        (new IncidentLifecycle())->assertTransition(IncidentStatus::IDENTIFIED, IncidentStatus::IDENTIFIED);
    }
}
