<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

enum ComponentStatus: string
{
    case OPERATIONAL = 'OPERATIONAL';
    case DEGRADED = 'DEGRADED';
    case PARTIAL_OUTAGE = 'PARTIAL_OUTAGE';
    case MAJOR_OUTAGE = 'MAJOR_OUTAGE';
    case MAINTENANCE = 'MAINTENANCE';

    public function weight(): int
    {
        return match ($this) {
            self::OPERATIONAL => 0,
            self::MAINTENANCE => 1,
            self::DEGRADED => 2,
            self::PARTIAL_OUTAGE => 3,
            self::MAJOR_OUTAGE => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Operational',
            self::DEGRADED => 'Degraded performance',
            self::PARTIAL_OUTAGE => 'Partial outage',
            self::MAJOR_OUTAGE => 'Major outage',
            self::MAINTENANCE => 'Maintenance',
        };
    }
}
