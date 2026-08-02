<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

final class OverallStatusCalculator
{
    /** @param iterable<ComponentStatus|string> $statuses */
    public function calculate(iterable $statuses): ComponentStatus
    {
        $overall = ComponentStatus::OPERATIONAL;
        foreach ($statuses as $status) {
            $candidate = $status instanceof ComponentStatus ? $status : ComponentStatus::from($status);
            if ($candidate->weight() > $overall->weight()) {
                $overall = $candidate;
            }
        }

        return $overall;
    }
}
