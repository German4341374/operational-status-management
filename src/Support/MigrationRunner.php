<?php

declare(strict_types=1);

namespace OperationalStatus\Support;

final class MigrationRunner
{
    public function __construct(private readonly \PDO $pdo, private readonly string $directory) {}

    /** @return list<string> */
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
        $this->pdo->exec("SELECT pg_advisory_lock(hashtext('operational-status-management-migrations'))");
        try {
            $files = glob(rtrim($this->directory, '/\\') . '/*.sql') ?: [];
            sort($files, SORT_STRING);
            $applied = [];
            foreach ($files as $file) {
                $version = basename($file, '.sql');
                $sql = file_get_contents($file);
                if (false === $sql) {
                    throw new \RuntimeException('Migration could not be read: ' . $version);
                }
                $checksum = hash('sha256', $sql);
                $statement = $this->pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = :version');
                $statement->execute(['version' => $version]);
                $existing = $statement->fetchColumn();
                if (false !== $existing) {
                    if (!hash_equals((string) $existing, $checksum)) {
                        throw new \RuntimeException('Applied migration checksum changed: ' . $version);
                    }
                    continue;
                }

                $this->pdo->beginTransaction();
                try {
                    $this->pdo->exec($sql);
                    $insert = $this->pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:version, :checksum)');
                    $insert->execute(['version' => $version, 'checksum' => $checksum]);
                    $this->pdo->commit();
                    $applied[] = $version;
                } catch (\Throwable $exception) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw new \RuntimeException('Migration failed and was rolled back: ' . $version, 0, $exception);
                }
            }

            return $applied;
        } finally {
            $this->pdo->exec("SELECT pg_advisory_unlock(hashtext('operational-status-management-migrations'))");
        }
    }
}
