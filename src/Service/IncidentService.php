<?php

declare(strict_types=1);

namespace OperationalStatus\Service;

use OperationalStatus\Cache\VersionedPublicPageCache;
use OperationalStatus\Domain\IncidentLifecycle;
use OperationalStatus\Domain\IncidentSeverity;
use OperationalStatus\Domain\IncidentStatus;
use OperationalStatus\Repository\StatusRepository;

final class IncidentService
{
    public function __construct(
        private readonly StatusRepository $repository,
        private readonly IncidentLifecycle $lifecycle,
        private readonly VersionedPublicPageCache $cache,
    ) {}

    /** @param list<string> $componentIds */
    public function create(string $title, string $description, IncidentSeverity $severity, array $componentIds, string $actor, ?string $requestId): string
    {
        $title = $this->required($title, 180, 'Incident title');
        $description = $this->required($description, 5000, 'Incident description');
        if ([] === $componentIds) {
            throw new \InvalidArgumentException('At least one component must be selected.');
        }

        $this->repository->begin();
        try {
            $incidentId = $this->repository->createIncident($title, $description, $severity, $componentIds, $actor);
            $this->repository->addIncidentUpdate($incidentId, IncidentStatus::INVESTIGATING->value, $description, $actor);
            foreach (array_unique($componentIds) as $componentId) {
                $this->repository->recomputeComponentStatus($componentId, 'INCIDENT', $incidentId);
            }
            $this->repository->audit('INCIDENT_CREATED', 'Incident', $incidentId, $actor, [
                'severity' => $severity->value,
                'components' => array_values(array_unique($componentIds)),
            ], $requestId);
            $this->cache->invalidate();
            $this->repository->commit();

            return $incidentId;
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw $exception;
        }
    }

    public function update(string $incidentId, IncidentStatus $nextStatus, string $message, int $expectedVersion, string $actor, ?string $requestId): void
    {
        $message = $this->required($message, 5000, 'Incident update');
        $this->repository->begin();
        try {
            $incident = $this->repository->findIncidentForUpdate($incidentId);
            if (null === $incident) {
                throw new \RuntimeException('Incident not found.');
            }
            $current = IncidentStatus::from((string) $incident['status']);
            $this->lifecycle->assertTransition($current, $nextStatus);
            if ((int) $incident['lock_version'] !== $expectedVersion) {
                throw new \DomainException('The incident changed after it was loaded. Reload and retry.');
            }

            $updateId = $this->repository->addIncidentUpdate($incidentId, $nextStatus->value, $message, $actor);
            $this->repository->updateIncidentStatus($incidentId, $nextStatus->value, $expectedVersion);
            foreach ($this->repository->incidentComponentIds($incidentId) as $componentId) {
                $this->repository->recomputeComponentStatus($componentId, 'INCIDENT', $incidentId);
            }
            $this->repository->audit('INCIDENT_UPDATED', 'Incident', $incidentId, $actor, [
                'from' => $current->value,
                'to' => $nextStatus->value,
                'updateId' => $updateId,
            ], $requestId);
            $this->cache->invalidate();
            $this->repository->commit();
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw $exception;
        }
    }

    private function required(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw new \InvalidArgumentException($field . ' is required.');
        }
        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(sprintf('%s must not exceed %d characters.', $field, $maxLength));
        }

        return $value;
    }
}
