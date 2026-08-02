<?php

declare(strict_types=1);

namespace OperationalStatus\Support;

final class DatabaseFactory
{
    public static function create(Config $config): \PDO
    {
        $pdo = new \PDO(
            $config->require('DATABASE_URL'),
            $config->require('DATABASE_USER'),
            $config->require('DATABASE_PASSWORD'),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
        $pdo->exec("SET TIME ZONE 'UTC'");

        return $pdo;
    }
}
