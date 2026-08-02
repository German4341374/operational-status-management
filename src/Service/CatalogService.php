<?php

declare(strict_types=1);

namespace OperationalStatus\Service;

use OperationalStatus\Cache\VersionedPublicPageCache;
use OperationalStatus\Repository\StatusRepository;

final class CatalogService
{
    public function __construct(private readonly StatusRepository $repository, private readonly VersionedPublicPageCache $cache) {}

    public function createGroup(string $name, string $description, string $actor, ?string $requestId): string
    {
        $name = $this->required($name, 120, 'Group name');
        $this->repository->begin();
        try {
            $id = $this->repository->createGroup($name, trim($description));
            $this->repository->audit('COMPONENT_GROUP_CREATED', 'ComponentGroup', $id, $actor, ['name' => $name], $requestId);
            $this->cache->invalidate();
            $this->repository->commit();

            return $id;
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw $exception;
        }
    }

    public function createComponent(string $name, string $description, ?string $groupId, bool $internal, string $actor, ?string $requestId): string
    {
        $name = $this->required($name, 120, 'Component name');
        $this->repository->begin();
        try {
            $id = $this->repository->createComponent($name, trim($description), $groupId, $internal);
            $this->repository->audit('COMPONENT_CREATED', 'Component', $id, $actor, ['name' => $name, 'internal' => $internal], $requestId);
            $this->cache->invalidate();
            $this->repository->commit();

            return $id;
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw $exception;
        }
    }

    /** @param list<string> $componentIds */
    public function scheduleMaintenance(string $title, string $description, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, array $componentIds, string $actor, ?string $requestId): string
    {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Maintenance must end after it starts.');
        }
        if ([] === $componentIds) {
            throw new \InvalidArgumentException('At least one component must be selected.');
        }
        $title = $this->required($title, 180, 'Maintenance title');
        $this->repository->begin();
        try {
            $id = $this->repository->createMaintenance($title, trim($description), $startsAt, $endsAt, $componentIds, $actor);
            $this->repository->audit('MAINTENANCE_SCHEDULED', 'ScheduledMaintenance', $id, $actor, ['components' => $componentIds], $requestId);
            $this->cache->invalidate();
            $this->repository->commit();

            return $id;
        } catch (\Throwable $exception) {
            $this->repository->rollback();
            throw $exception;
        }
    }

    private function required(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ('' === $value || mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException($field . ' is invalid.');
        }

        return $value;
    }
}
