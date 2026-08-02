<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

enum IncidentSeverity: string
{
    case MINOR = 'MINOR';
    case MAJOR = 'MAJOR';
    case CRITICAL = 'CRITICAL';

    public function componentStatus(): ComponentStatus
    {
        return match ($this) {
            self::MINOR => ComponentStatus::DEGRADED,
            self::MAJOR => ComponentStatus::PARTIAL_OUTAGE,
            self::CRITICAL => ComponentStatus::MAJOR_OUTAGE,
        };
    }
}
