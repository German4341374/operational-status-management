<?php

declare(strict_types=1);

namespace OperationalStatus\Repository;

use OperationalStatus\Domain\ComponentStatus;
use OperationalStatus\Domain\IncidentSeverity;
use OperationalStatus\Support\Uuid;

final class StatusRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function groupsWithComponents(bool $includeInternal): array
    {
        $groups = $this->requiredQuery('SELECT id, name, description FROM component_groups ORDER BY display_order, name')->fetchAll();
        $statement = $this->pdo->prepare(
            'SELECT id, group_id, name, description, status, is_internal FROM components WHERE (:include_internal OR NOT is_internal) ORDER BY display_order, name',
        );
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->execute();
        $components = $statement->fetchAll();
        foreach ($groups as &$group) {
            $group['components'] = array_values(array_filter(
                $components,
                static fn(array $component): bool => $component['group_id'] === $group['id'],
            ));
        }
        unset($group);

        return array_values(array_filter($groups, static fn(array $group): bool => [] !== $group['components']));
    }

    /** @return list<array<string, mixed>> */
    public function activeIncidents(bool $includeInternal): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT i.* FROM incidents i
             JOIN incident_components ic ON ic.incident_id = i.id
             JOIN components c ON c.id = ic.component_id
             WHERE i.status <> 'RESOLVED' AND (:include_internal OR NOT c.is_internal)
             ORDER BY i.started_at DESC",
        );
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->execute();
        $incidents = $statement->fetchAll();
        foreach ($incidents as &$incident) {
            $incident['components'] = $this->incidentComponents((string) $incident['id'], $includeInternal);
            $incident['updates'] = $this->incidentUpdates((string) $incident['id']);
        }
        unset($incident);

        return array_values($incidents);
    }

    /** @return list<array<string, mixed>> */
    public function recentIncidents(bool $includeInternal, int $limit = 30): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT i.* FROM incidents i
             JOIN incident_components ic ON ic.incident_id = i.id
             JOIN components c ON c.id = ic.component_id
             WHERE (:include_internal OR NOT c.is_internal)
             ORDER BY i.started_at DESC LIMIT :limit',
        );
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->bindValue('limit', min(max($limit, 1), 100), \PDO::PARAM_INT);
        $statement->execute();
        $incidents = $statement->fetchAll();
        foreach ($incidents as &$incident) {
            $incident['components'] = $this->incidentComponents((string) $incident['id'], $includeInternal);
            $incident['updates'] = $this->incidentUpdates((string) $incident['id']);
        }
        unset($incident);

        return array_values($incidents);
    }

    /** @return list<array<string, mixed>> */
    public function maintenance(bool $includeInternal, int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT m.* FROM scheduled_maintenance m
             JOIN maintenance_components mc ON mc.maintenance_id = m.id
             JOIN components c ON c.id = mc.component_id
             WHERE m.status IN ('SCHEDULED', 'IN_PROGRESS') AND (:include_internal OR NOT c.is_internal)
             ORDER BY m.starts_at LIMIT :limit",
        );
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->bindValue('limit', min(max($limit, 1), 100), \PDO::PARAM_INT);
        $statement->execute();
        $maintenance = $statement->fetchAll();
        foreach ($maintenance as &$window) {
            $components = $this->pdo->prepare(
                'SELECT c.id, c.name FROM components c JOIN maintenance_components mc ON mc.component_id = c.id WHERE mc.maintenance_id = :id AND (:include_internal OR NOT c.is_internal) ORDER BY c.name',
            );
            $components->bindValue('id', $window['id'], \PDO::PARAM_STR);
            $components->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
            $components->execute();
            $window['components'] = $components->fetchAll();
        }
        unset($window);

        return array_values($maintenance);
    }

    /** @return list<array<string, mixed>> */
    public function components(bool $includeInternal = true): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM components WHERE (:include_internal OR NOT is_internal) ORDER BY display_order, name');
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->execute();

        return array_values($statement->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public function groups(): array
    {
        return array_values($this->requiredQuery('SELECT * FROM component_groups ORDER BY display_order, name')->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findIncidentForUpdate(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM incidents WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $incident = $statement->fetch();

        return false === $incident ? null : $incident;
    }

    /** @return list<string> */
    public function incidentComponentIds(string $incidentId): array
    {
        $statement = $this->pdo->prepare('SELECT component_id FROM incident_components WHERE incident_id = :id');
        $statement->execute(['id' => $incidentId]);

        return array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /** @return list<array{status: string, changed_at: string}> */
    public function historyForComponent(string $componentId, \DateTimeImmutable $since): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, changed_at FROM status_history WHERE component_id = :component_id AND changed_at >= :since ORDER BY changed_at DESC',
        );
        $statement->execute(['component_id' => $componentId, 'since' => $since->format(DATE_ATOM)]);

        $history = [];
        foreach ($statement->fetchAll() as $row) {
            $status = $row['status'] ?? null;
            $changedAt = $row['changed_at'] ?? null;
            if (!is_string($status) || !is_string($changedAt)) {
                throw new \RuntimeException('Status history row has an unexpected shape.');
            }
            $history[] = ['status' => $status, 'changed_at' => $changedAt];
        }

        return $history;
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** @param list<string> $componentIds */
    public function createIncident(string $title, string $description, IncidentSeverity $severity, array $componentIds, string $actor): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            "INSERT INTO incidents (id, title, description, severity, status, started_at, created_by) VALUES (:id, :title, :description, :severity, 'INVESTIGATING', NOW(), :actor)",
        );
        $statement->execute(compact('id', 'title', 'description', 'actor') + ['severity' => $severity->value]);
        $link = $this->pdo->prepare('INSERT INTO incident_components (incident_id, component_id) VALUES (:incident_id, :component_id)');
        foreach (array_unique($componentIds) as $componentId) {
            $link->execute(['incident_id' => $id, 'component_id' => $componentId]);
        }

        return $id;
    }

    public function addIncidentUpdate(string $incidentId, string $status, string $message, string $actor): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            'INSERT INTO incident_updates (id, incident_id, status, message, created_by) VALUES (:id, :incident_id, :status, :message, :actor)',
        );
        $statement->execute([
            'id' => $id,
            'incident_id' => $incidentId,
            'status' => $status,
            'message' => $message,
            'actor' => $actor,
        ]);

        return $id;
    }

    public function updateIncidentStatus(string $incidentId, string $status, int $expectedVersion): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE incidents SET status = :status, resolved_at = CASE WHEN :status = 'RESOLVED' THEN NOW() ELSE NULL END, updated_at = NOW(), lock_version = lock_version + 1 WHERE id = :id AND lock_version = :expected_version",
        );
        $statement->execute(['status' => $status, 'id' => $incidentId, 'expected_version' => $expectedVersion]);
        if (1 !== $statement->rowCount()) {
            throw new \DomainException('The incident changed after it was loaded. Reload and retry.');
        }
    }

    public function recomputeComponentStatus(string $componentId, string $sourceType, ?string $sourceId): ComponentStatus
    {
        $statement = $this->pdo->prepare(
            "SELECT i.severity FROM incidents i JOIN incident_components ic ON ic.incident_id = i.id
             WHERE ic.component_id = :component_id AND i.status <> 'RESOLVED'",
        );
        $statement->execute(['component_id' => $componentId]);
        $status = ComponentStatus::OPERATIONAL;
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $severity) {
            $candidate = IncidentSeverity::from((string) $severity)->componentStatus();
            if ($candidate->weight() > $status->weight()) {
                $status = $candidate;
            }
        }
        if (ComponentStatus::OPERATIONAL === $status) {
            $maintenance = $this->pdo->prepare(
                "SELECT EXISTS(SELECT 1 FROM scheduled_maintenance m JOIN maintenance_components mc ON mc.maintenance_id = m.id
                 WHERE mc.component_id = :component_id AND m.status = 'IN_PROGRESS' AND NOW() BETWEEN m.starts_at AND m.ends_at)",
            );
            $maintenance->execute(['component_id' => $componentId]);
            if ((bool) $maintenance->fetchColumn()) {
                $status = ComponentStatus::MAINTENANCE;
            }
        }

        $update = $this->pdo->prepare('UPDATE components SET status = :status, updated_at = NOW() WHERE id = :id AND status <> :status');
        $update->execute(['status' => $status->value, 'id' => $componentId]);
        if (1 === $update->rowCount()) {
            $history = $this->pdo->prepare(
                'INSERT INTO status_history (component_id, status, source_type, source_id) VALUES (:component_id, :status, :source_type, :source_id)',
            );
            $history->execute(['component_id' => $componentId, 'status' => $status->value, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        }

        return $status;
    }

    /** @param array<string, mixed> $details */
    public function audit(string $action, string $aggregateType, ?string $aggregateId, string $actor, array $details, ?string $requestId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_log (action, aggregate_type, aggregate_id, actor, details, request_id) VALUES (:action, :aggregate_type, :aggregate_id, :actor, CAST(:details AS JSONB), :request_id)',
        );
        $statement->execute([
            'action' => $action,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'actor' => $actor,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'request_id' => $requestId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function auditEvents(int $limit = 50): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue('limit', min(max($limit, 1), 200), \PDO::PARAM_INT);
        $statement->execute();

        return array_values($statement->fetchAll());
    }

    public function createGroup(string $name, string $description): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare('INSERT INTO component_groups (id, name, description) VALUES (:id, :name, :description)');
        $statement->execute(compact('id', 'name', 'description'));

        return $id;
    }

    public function createComponent(string $name, string $description, ?string $groupId, bool $internal): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            "INSERT INTO components (id, group_id, name, description, status, is_internal) VALUES (:id, :group_id, :name, :description, 'OPERATIONAL', :internal)",
        );
        $statement->bindValue('id', $id, \PDO::PARAM_STR);
        $statement->bindValue('group_id', $groupId, null === $groupId ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $statement->bindValue('name', $name, \PDO::PARAM_STR);
        $statement->bindValue('description', $description, \PDO::PARAM_STR);
        $statement->bindValue('internal', $internal, \PDO::PARAM_BOOL);
        $statement->execute();
        $history = $this->pdo->prepare("INSERT INTO status_history (component_id, status, source_type) VALUES (:id, 'OPERATIONAL', 'SYSTEM')");
        $history->execute(['id' => $id]);

        return $id;
    }

    /** @param list<string> $componentIds */
    public function createMaintenance(string $title, string $description, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, array $componentIds, string $actor): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            "INSERT INTO scheduled_maintenance (id, title, description, status, starts_at, ends_at, created_by) VALUES (:id, :title, :description, 'SCHEDULED', :starts_at, :ends_at, :actor)",
        );
        $statement->execute([
            'id' => $id, 'title' => $title, 'description' => $description,
            'starts_at' => $startsAt->format(DATE_ATOM), 'ends_at' => $endsAt->format(DATE_ATOM), 'actor' => $actor,
        ]);
        $link = $this->pdo->prepare('INSERT INTO maintenance_components (maintenance_id, component_id) VALUES (:maintenance_id, :component_id)');
        foreach (array_unique($componentIds) as $componentId) {
            $link->execute(['maintenance_id' => $id, 'component_id' => $componentId]);
        }

        return $id;
    }

    public function subscribe(string $subscriberHash, string $scope): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO subscriptions (id, subscriber_hash, scope) VALUES (:id, :subscriber_hash, :scope) ON CONFLICT (subscriber_hash) DO UPDATE SET active = TRUE, scope = EXCLUDED.scope',
        );

        return $statement->execute(['id' => Uuid::v4(), 'subscriber_hash' => $subscriberHash, 'scope' => $scope]);
    }

    /** @return list<array<string, mixed>> */
    private function incidentComponents(string $incidentId, bool $includeInternal): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name, c.status FROM components c JOIN incident_components ic ON ic.component_id = c.id WHERE ic.incident_id = :id AND (:include_internal OR NOT c.is_internal) ORDER BY c.name',
        );
        $statement->bindValue('id', $incidentId, \PDO::PARAM_STR);
        $statement->bindValue('include_internal', $includeInternal, \PDO::PARAM_BOOL);
        $statement->execute();

        return array_values($statement->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    private function incidentUpdates(string $incidentId): array
    {
        $statement = $this->pdo->prepare('SELECT id, status, message, created_by, created_at FROM incident_updates WHERE incident_id = :id ORDER BY created_at DESC');
        $statement->execute(['id' => $incidentId]);

        return array_values($statement->fetchAll());
    }

    private function requiredQuery(string $sql): \PDOStatement
    {
        $statement = $this->pdo->query($sql);
        if (false === $statement) {
            throw new \RuntimeException('Database query could not be prepared.');
        }

        return $statement;
    }
}
