<?php

declare(strict_types=1);

namespace OperationalStatus\Domain;

final class UptimeCalculator
{
    /**
     * @param list<array{status: string, changed_at: string}> $history Newest first.
     */
    public function percentage(array $history, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        if ($to <= $from) {
            throw new \InvalidArgumentException('Uptime interval must have a positive duration.');
        }

        $events = array_map(
            static fn(array $row): array => ['status' => ComponentStatus::from($row['status']), 'at' => new \DateTimeImmutable($row['changed_at'])],
            $history,
        );
        usort($events, static fn(array $left, array $right): int => $left['at'] <=> $right['at']);

        $current = ComponentStatus::OPERATIONAL;
        foreach ($events as $event) {
            if ($event['at'] <= $from) {
                $current = $event['status'];
            }
        }

        $cursor = $from;
        $availableSeconds = 0;
        foreach ($events as $event) {
            if ($event['at'] <= $from || $event['at'] >= $to) {
                continue;
            }
            if (ComponentStatus::OPERATIONAL === $current || ComponentStatus::MAINTENANCE === $current) {
                $availableSeconds += $event['at']->getTimestamp() - $cursor->getTimestamp();
            }
            $cursor = $event['at'];
            $current = $event['status'];
        }
        if (ComponentStatus::OPERATIONAL === $current || ComponentStatus::MAINTENANCE === $current) {
            $availableSeconds += $to->getTimestamp() - $cursor->getTimestamp();
        }

        $total = $to->getTimestamp() - $from->getTimestamp();

        return round(($availableSeconds / $total) * 100, 3);
    }
}
