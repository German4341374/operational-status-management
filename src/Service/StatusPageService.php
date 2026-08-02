<?php

declare(strict_types=1);

namespace OperationalStatus\Service;

use OperationalStatus\Domain\OverallStatusCalculator;
use OperationalStatus\Domain\UptimeCalculator;
use OperationalStatus\Repository\StatusRepository;

final class StatusPageService
{
    public function __construct(
        private readonly StatusRepository $repository,
        private readonly OverallStatusCalculator $overall,
        private readonly UptimeCalculator $uptime,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(bool $includeInternal): array
    {
        $groups = $this->repository->groupsWithComponents($includeInternal);
        $statuses = [];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $since = $now->sub(new \DateInterval('P90D'));
        foreach ($groups as &$group) {
            foreach ($group['components'] as &$component) {
                $statuses[] = (string) $component['status'];
                $component['uptime'] = $this->uptime->percentage(
                    $this->repository->historyForComponent((string) $component['id'], $since),
                    $since,
                    $now,
                );
            }
            unset($component);
        }
        unset($group);

        return [
            'generatedAt' => $now->format(DATE_ATOM),
            'overall' => $this->overall->calculate($statuses),
            'groups' => $groups,
            'incidents' => $this->repository->activeIncidents($includeInternal),
            'maintenance' => $this->repository->maintenance($includeInternal),
        ];
    }

    /** @return array<string, mixed> */
    public function history(bool $includeInternal): array
    {
        return [
            'incidents' => $this->repository->recentIncidents($includeInternal, 50),
            'components' => $this->repository->components($includeInternal),
        ];
    }
}
