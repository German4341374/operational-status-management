<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

final class IncidentLifecycle
{
    /** @var array<string, list<IncidentStatus>> */
    private const array TRANSITIONS = [
        IncidentStatus::INVESTIGATING->value => [IncidentStatus::IDENTIFIED, IncidentStatus::MONITORING, IncidentStatus::RESOLVED],
        IncidentStatus::IDENTIFIED->value => [IncidentStatus::MONITORING, IncidentStatus::RESOLVED],
        IncidentStatus::MONITORING->value => [IncidentStatus::IDENTIFIED, IncidentStatus::RESOLVED],
        IncidentStatus::RESOLVED->value => [],
    ];

    public function assertTransition(IncidentStatus $from, IncidentStatus $to): void
    {
        if ($from === $to || !in_array($to, self::TRANSITIONS[$from->value], true)) {
            throw new \DomainException(sprintf('Incident cannot transition from %s to %s.', $from->value, $to->value));
        }
    }
}
