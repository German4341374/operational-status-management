<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

enum IncidentStatus: string
{
    case INVESTIGATING = 'INVESTIGATING';
    case IDENTIFIED = 'IDENTIFIED';
    case MONITORING = 'MONITORING';
    case RESOLVED = 'RESOLVED';

    public function isActive(): bool
    {
        return self::RESOLVED !== $this;
    }
}
