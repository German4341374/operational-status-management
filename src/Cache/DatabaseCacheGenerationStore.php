<?php

declare(strict_types=1);

namespace OperationalStatus\Cache;

final class DatabaseCacheGenerationStore implements CacheGenerationStore
{
    public function __construct(private readonly \PDO $pdo) {}

    public function current(): int
    {
        $statement = $this->pdo->query("SELECT generation FROM cache_generations WHERE cache_key = 'public_status'");
        if (false === $statement) {
            throw new \RuntimeException('Public cache generation could not be read.');
        }
        $value = $statement->fetchColumn();

        return false === $value ? 1 : (int) $value;
    }

    public function increment(): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE cache_generations SET generation = generation + 1, updated_at = NOW() WHERE cache_key = 'public_status' RETURNING generation",
        );
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
